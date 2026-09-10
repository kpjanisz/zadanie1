<?php

declare(strict_types=1);

namespace App\Shared\Http;

use App\Shared\Exception\PreconditionFailedException;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Conditional-write plumbing, shared by every resource that has a validator.
 *
 * Takes the validator as a ready string, so this stays free of any domain
 * knowledge — each module computes its own (ProductEtag, CategoryEtag) and the
 * module graph stays acyclic.
 */
final readonly class PreconditionChecker
{
    public function __construct(
        private RequestStack $requestStack,
    ) {
    }

    /**
     * Enforces If-Match: the header describes the state the client believes it
     * is modifying, so a mismatch means somebody wrote in between. This is what
     * closes the read-think-write window that #[ORM\Version] cannot see — its
     * check only spans a single request.
     *
     * An absent header means the client is not doing conditional writes; the
     * server-side version check still guards the narrow window.
     *
     * The comparison is on state alone. A client obtains its validator from a
     * GET, whose format it negotiated then; the PATCH that follows negotiates
     * again, and the two rarely agree — a PATCH sends no Accept of its own, so
     * it lands on whatever the server prefers. Demanding the format match would
     * fail a client that changed nothing, and the remedy the 412 prescribes —
     * re-read and retry — would fail identically every time round.
     */
    public function assertMatches(string $stateFingerprint): void
    {
        $ifMatch = $this->requestStack->getMainRequest()?->headers->get('If-Match');

        if (null === $ifMatch || '' === $ifMatch) {
            return;
        }

        foreach (array_map(trim(...), explode(',', $ifMatch)) as $offered) {
            if ('*' === $offered || $this->describesSameState($offered, $stateFingerprint)) {
                return;
            }
        }

        throw new PreconditionFailedException();
    }

    /**
     * Whether an offered entity-tag names the state we are about to overwrite.
     *
     * Accepts the tag in either shape: the qualified one this application emits
     * ("<state>.<format>") and the bare state, because a validator that has
     * travelled through a client library may arrive stripped.
     *
     * W/"..." is rejected rather than unwrapped: If-Match is defined to use
     * strong comparison (RFC 9110 §13.1.1), and a weak validator promises
     * semantic equivalence, not identical state — not enough to authorise a
     * write.
     */
    private function describesSameState(string $offered, string $stateFingerprint): bool
    {
        if (1 !== preg_match('/^"(.*)"$/', $offered, $tag)) {
            return false;
        }

        return $tag[1] === $stateFingerprint || str_starts_with($tag[1], $stateFingerprint.'.');
    }

    /**
     * Leaves the validator for ResourceEtagSubscriber to put on the response.
     * After a write it must describe the state the client now has, not the one
     * it replaced.
     */
    public function remember(string $stateFingerprint): void
    {
        $this->requestStack->getMainRequest()?->attributes->set(
            ResourceEtag::REQUEST_ATTRIBUTE,
            $this->qualify($stateFingerprint),
        );
    }

    /**
     * Turns a state fingerprint into the ETag of one concrete representation.
     *
     * State says nothing about format, but the same product serialised as
     * JSON-LD and as plain JSON are different documents — 343 bytes against 172.
     * A shared cache is covered by Vary: Accept, but a client is not: it would
     * send the validator it got for JSON-LD while asking for JSON, receive 304,
     * and treat its JSON-LD copy as the answer.
     *
     * That cuts one way only. An ETag identifies a representation, so emitting
     * it qualified is right; If-Match asks a question about resource state, so
     * comparing it qualified is not (see assertMatches).
     */
    private function qualify(string $stateFingerprint): string
    {
        $format = $this->requestStack->getMainRequest()?->getRequestFormat() ?? 'unknown';

        return \sprintf('"%s.%s"', $stateFingerprint, $format);
    }
}
