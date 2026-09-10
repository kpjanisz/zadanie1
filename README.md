# REST API — produkty i kategorie

Zadanie rekrutacyjne: REST API do zarządzania produktami i kategoriami, z **rozszerzalnym systemem powiadomień** wyzwalanym po zapisie produktu.

Stack: **PHP 8.4 · Symfony 8.1 · API Platform 4.3 · Doctrine ORM 3.7 · MySQL 8.4 · Redis 7 · Docker**

---

## Spis treści

1. [Wymagania](#wymagania)
2. [Uruchomienie](#uruchomienie)
3. [Konfiguracja](#konfiguracja)
4. [Autoryzacja](#autoryzacja)
5. [Endpointy](#endpointy)
6. [Architektura powiadomień](#architektura-powiadomień) ← rdzeń zadania
7. [Testy](#testy)
8. [Jakość kodu](#jakość-kodu)
9. [Struktura projektu](#struktura-projektu)
10. [Decyzje projektowe](#decyzje-projektowe)

---

## Wymagania

Tylko **Docker** (z Docker Compose v2+). PHP, Composer i MySQL działają w kontenerach — nic nie trzeba instalować na hoście.

Albo **nic** — projekt startuje w GitHub Codespaces jednym kliknięciem (wariant A poniżej).

W WSL2 upewnij się, że w Docker Desktop włączona jest integracja z dystrybucją (*Settings → Resources → WSL Integration*).

## Uruchomienie

### Wariant A — GitHub Codespaces (bez instalowania czegokolwiek)

**Code → Codespaces → Create codespace on main.** Reszta dzieje się sama: `.devcontainer/` startuje ten sam stack z `compose.yaml`, instaluje zależności, generuje klucze JWT, wykonuje migracje i ładuje dane demonstracyjne. Pierwsze uruchomienie to kilka minut (budowanie obrazów), kolejne są natychmiastowe.

Gdy skrypt skończy, otwórz zakładkę **PORTS** i kliknij adres przy porcie **8080** — dopisując `/console.html` trafisz od razu do konsoli. Port **8025** to Mailpit.

Adresy `localhost` **działają normalnie w terminalu codespace'a**, więc wszystkie polecenia `make` i demo curl-em z tego README przechodzą bez zmian. Zmienia się tylko adres w przeglądarce, bo Codespaces przekierowuje porty przez HTTPS.

> Każdy codespace to osobna maszyna — **własna baza, własny Redis, własne dane**. Nic nie jest współdzielone między osobami.

**Zostaw porty prywatne.** Codespaces przekierowuje je jako *Private* — adres otwiera wyłącznie osoba, która utworzyła codespace'a — i tak ma zostać. Przestawienie portu 8080 na *Public* daje anonimowy dostęp do profilera Symfony (`/_profiler` w `dev` celowo omija firewall), do konsoli i do konta `admin@example.test`, którego hasło jest jawne w tym README. Traci też sens limiter per-IP: przez tunel wszystkie żądania przychodzą z adresu wewnętrznego, więc `X-Forwarded-For` staje się nagłówkiem od klienta.

Nic z tego nie jest wadą samego projektu — to konsekwencja wystawienia środowiska **deweloperskiego** w internet. Jeśli mimo wszystko trzeba pokazać instancję linkiem: `APP_ENV=prod` w `.env.local` (profiler i Swagger znikają), inne hasło w fixtures, port 3306 zostawiony prywatny.

### Wariant B — lokalnie

```bash
make up          # zbudowanie obrazów i start kontenerów (php, nginx, mysql, redis, mailer)
make install     # instalacja zależności PHP
make jwt         # wygenerowanie par kluczy JWT (dev i test)
make migrate     # utworzenie schematu bazy
make fixtures    # dane demonstracyjne (2 użytkowników, 4 kategorie, 5 produktów)
```

Po tym:

| Co | Gdzie |
|---|---|
| API | http://localhost:8080/api |
| **Konsola do klikania** | http://localhost:8080/console.html |
| Sonda zdrowia | http://localhost:8080/health |
| Dokumentacja OpenAPI (Swagger UI) | http://localhost:8080/api/docs — **tylko w `dev`** |
| Skrzynka mailowa (Mailpit) | http://localhost:8025 |
| MySQL | `localhost:3306`, baza `app`, user `app` / `app` |
| Redis | wewnętrznie `redis:6379` — stan limitera i rewokacji tokenów |

> **Uwaga o passphrase JWT.** `make jwt` używa `JWT_PASSPHRASE`. Jeśli nie utworzysz `.env.local`, użyta zostanie wartość domyślna z `.env`. Na potrzeby oceny to wystarczy; w realnym wdrożeniu passphrase trafia wyłącznie do `.env.local` (plik jest w `.gitignore`, tak jak same klucze).

Pozostałe polecenia: `make help`.

### Konsola — najszybszy sposób, żeby to przejrzeć

**http://localhost:8080/console.html**

Jeden statyczny plik, bez build-stepu i bez zależności, serwowany z tego samego origin (więc bez CORS). Logujesz się raz, token siedzi w `sessionStorage` karty, a potem klikasz:

- **Produkty** — filtry (nazwa, kod kategorii, zakres cen, sortowanie, rozmiar strony), tworzenie, edycja przez PATCH, usuwanie
- **Kategorie** — lista, tworzenie, usuwanie (zobaczysz `409` przy kodzie w użyciu)
- **Log operacji** — ślad audytowy; utwórz produkt i odśwież, żeby zobaczyć efekt systemu powiadomień bez zaglądania do SQL-a
- **Uwierzytelnianie** — logowanie, odświeżenie tokenu, wylogowanie, podgląd zdekodowanych claimów i odliczanie do wygaśnięcia

Panel **Ostatnie żądanie** pokazuje metodę, ścieżkę, kod odpowiedzi, czas i pełne ciało — to narzędzie do testowania, a nie tylko interfejs. Naruszenia walidacji renderują się z `propertyPath`, więc od razu widać, które pole odpadło i dlaczego.

> Konsola to narzędzie deweloperskie. Nie udostępnia niczego ponad to, co API, ale nie ma czego szukać na produkcji — pomiń plik przy budowaniu obrazu albo odkomentuj gotowy blok `location = /console.html` w [docker/nginx/default.conf](docker/nginx/default.conf).

### Pierwsze żądanie (curl)

```bash
# 1. token
TOKEN=$(curl -s -X POST http://localhost:8080/api/auth/login \
  -H 'Content-Type: application/json' \
  -d '{"email":"admin@example.test","password":"DevPassw0rd!"}' | jq -r .token)

# 2. lista produktów
curl -s -H "Authorization: Bearer $TOKEN" http://localhost:8080/api/products | jq

# 3. nowy produkt — zobacz efekty w Mailpicie i w tabeli operation_log
curl -s -X POST http://localhost:8080/api/products \
  -H "Authorization: Bearer $TOKEN" \
  -H 'Content-Type: application/ld+json' \
  -d '{"name":"Pralka Bosch","price":"1299.50","categoryIds":[1,2]}' | jq
```

## Konfiguracja

`.env` zawiera **wyłącznie bezpieczne wartości domyślne** dla Dockera i jest wersjonowany. Sekrety trafiają do `.env.local` (ignorowany przez git).

Żaden wersjonowany plik nie niesie realnej wartości sekretu — `APP_SECRET` i `JWT_PASSPHRASE` to jawne placeholdery, a pary kluczy JWT (`config/jwt/`) powstają lokalnie i są w `.gitignore`. Dlatego **repozytorium może być publiczne**; poświadczenia z tego README (`app`/`app`, `admin@example.test`) działają wyłącznie wewnątrz sieci Dockera na maszynie, która je uruchomiła.

| Zmienna | Domyślnie | Znaczenie |
|---|---|---|
| `HTTP_PORT` | `8080` | port API na hoście |
| `MAILPIT_PORT` | `8025` | port webowej skrzynki Mailpit |
| `MYSQL_PORT` | `3306` | port MySQL na hoście |
| `DATABASE_URL` | `mysql://app:app@mysql:3306/app?serverVersion=8.4&charset=utf8mb4` | połączenie z bazą |
| `MAILER_DSN` | `smtp://mailer:1025` | transport maili (lokalnie Mailpit) |
| `MAILER_FROM` | `no-reply@example.test` | nadawca |
| `NOTIFICATION_RECIPIENT` | `ops@example.test` | adresat powiadomień; pusty **wyłącza** kanał e-mail |
| `APP_SECRET` | placeholder | klucz HMAC frameworka — **w realnym wdrożeniu tylko w `.env.local`** |
| `JWT_PASSPHRASE` | placeholder | hasło klucza prywatnego JWT — **w realnym wdrożeniu tylko w `.env.local`** |
| `CORS_ALLOW_ORIGIN` | `http://localhost:8080` | dozwolone origins (bez wildcardu) |
| `TRUSTED_PROXIES` | `private_ranges` | pozwala odczytać realne IP klienta zza nginx |
| `REDIS_URL` | `redis://redis:6379` | współdzielony stan bezpieczeństwa (baza `/1` w testach) |

Stack można uruchomić w trybie produkcyjnym — wpisz `APP_ENV=prod` do `.env.local` i wykonaj `make down && make up`. Wtedy `/api` i `/api/docs` zwracają **404**, a odpowiedzi błędów nie zawierają stack trace'ów ani ścieżek na dysku.

## Autoryzacja

API jest **stateless** — brak sesji PHP, tożsamość podróżuje w nagłówku `Authorization: Bearer <token>`.

| Endpoint | Opis |
|---|---|
| `POST /api/auth/login` | `{"email":…,"password":…}` → `{"token":…,"refresh_token":…}` |
| `POST /api/auth/refresh` | `{"refresh_token":…}` → nowa para tokenów |
| `POST /api/auth/logout` | `{"refresh_token":…}` + nagłówek `Authorization` → kończy sesję: kasuje refresh token i unieważnia access token |

**Access token** żyje 15 minut. Długowieczność to zadanie **refresh tokenu**, który:

- podlega **rotacji** (`single_use`) — użyty raz, przestaje działać (potwierdzone testem: ponowne użycie → `401`),
- jest przechowywany w bazie **jako hash** (`sha256$…`), więc zrzut bazy nie rozdaje sesji,
- ma TTL 30 dni **bez przesuwania okna** — skradzionego tokenu nie da się utrzymywać przy życiu w nieskończoność,
- jest ograniczony do 5 aktywnych na użytkownika.

**Wykrywanie ponownego użycia** jest włączone: odtworzenie zrotowanego tokenu unieważnia całą rodzinę, do której należał (kolumna `family`) — bo replay zrotowanego tokenu to mocny sygnał kradzieży.

**Zakończenie sesji.** Sesja to dwa poświadczenia, więc `POST /api/auth/logout` odbiera oba: kasuje refresh token **i** wpisuje przedstawiony access token na blocklistę (`jti`, pula `cache.auth_revocation`) — po wylogowaniu przestaje działać od razu, bez czekania na resztę swoich 15 minut. Pokryte testem, który sprawdza **skutek** (żądanie do API po wylogowaniu → `401`), a nie samą odmowę odświeżenia.

Blocklista jest celowo **per token, nie per użytkownik**: wylogowanie na laptopie nie wylogowuje telefonu (też pokryte testem). Konsekwencja, o której trzeba wiedzieć: **klient musi wysłać nagłówek `Authorization` przy wylogowaniu** — JWT to poświadczenie na okaziciela, a tego, którego serwer nigdy nie zobaczył, nie ma czym unieważnić. Bez nagłówka refresh token i tak znika (sesji nie da się odnowić), ale access token dożywa swoich 15 minut. Konsola deweloperska nagłówek wysyła.

**Przejęte konto** wyłącza się natychmiast — komendą, nie kasowaniem wiersza:

```bash
docker exec zadanie-rekrutacyjne-php-1 php bin/console app:user:set-active <email>
docker exec zadanie-rekrutacyjne-php-1 php bin/console app:user:set-active <email> --enable
```

Dezaktywacja rewokuje wszystkie refresh tokeny użytkownika, a ponieważ `block_jwts_on_revocation` jest włączone, **przestają też działać wydane wcześniej JWT** — bez czekania na ich wygaśnięcie. Dodatkowo `UserChecker` odrzuca nieaktywne konto przy każdym żądaniu. Pokryte testami.

**Role:**

| Operacja | Wymaganie |
|---|---|
| `GET` (lista i pojedynczy zasób) | `IS_AUTHENTICATED_FULLY` |
| `POST` / `PATCH` / `DELETE` | `ROLE_ADMIN` |

Konta z fixtures: `admin@example.test` (ROLE_ADMIN) i `viewer@example.test` (ROLE_USER), hasło `DevPassw0rd!`.
Własne konto: `docker exec zadanie-rekrutacyjne-php-1 php bin/console app:user:create <email> <hasło> --admin`.

**Ochrona przed enumeracją kont.** Nieudane logowanie kosztuje tyle samo niezależnie od tego, czy konto istnieje. Bez tego różnica wynosiła 350 ms vs 18 ms — wiarygodne źródło listy prawidłowych adresów, mimo że oba przypadki zwracają `401`. Realizuje to `TimingSafeUserProvider`; pilnuje tego test jednostkowy.

**Dodatkowe zabezpieczenia:** throttling logowania (5 prób/min na IP+login), globalny limiter 300 żądań/min na IP dla `/api` (odrzucenie → `429` z `Retry-After`, RFC 7807), CORS bez wildcardu, Swagger tylko w `dev`, nagłówki HSTS / `Referrer-Policy` / `Permissions-Policy` / `nosniff` na warstwie nginx (także na błędach, które nie docierają do PHP). W `prod` osobny handler monologa audytuje kanał `security`, więc **próby logowania zostawiają ślad** — pod `fingers_crossed` ginęły w buforze.

### Stan bezpieczeństwa w Redisie

Liczniki limitera, znaczniki rewokacji JWT i zużyte refresh tokeny **muszą być wspólne dla wszystkich instancji**. Na magazynie lokalnym limit 300/min staje się 300 × liczba replik, a wyłączone konto pozostaje zalogowane wszędzie tam, gdzie znacznik nie dotarł.

Co tam trafia — i czego tam nie ma:

| Klucz | Wartość | TTL |
|---|---|---|
| `…_spent_<sha256(refresh token)>` | `{family, username}` | 30 dni |
| `…_revoked_before_<sha256(login)>` | uniksowy timestamp | 1800 s |
| `<sha1(id limitera)>` | okno przesuwne + IP klienta | 1 min |

**Nie da się tym uwierzytelnić** — refresh token istnieje wyłącznie jako hash w kluczu, haseł ani JWT tam nie ma. Trafiają tam natomiast dane osobowe (adresy IP i e-mail), więc Redis wymaga hasła i sieci prywatnej — to nie jest „tylko cache".

**Pojemność do policzenia przed wdrożeniem.** Rotacja jest jednorazowa, więc każde odświeżenie tworzy wpis żyjący 30 dni: 10 000 użytkowników × 96 odświeżeń dziennie × 30 dni ≈ 28,8 mln kluczy ≈ ~3 GB. Dlatego `maxmemory-policy` jest ustawione na `noeviction` — ciche wyeksmitowanie znacznika rewokacji degradowałoby bezpieczeństwo bez śladu, a odmowa zapisu jest głośna. Alternatywy: krótszy `reuse_detection.ttl` kosztem okna wykrywania albo dłuższy access token.

## Endpointy

### Kategorie — `/api/categories`

| Metoda | Ścieżka | Opis |
|---|---|---|
| `GET` | `/api/categories` | lista (paginacja po 30) |
| `GET` | `/api/categories/{id}` | szczegóły |
| `POST` | `/api/categories` | `{"code":"AGD"}` |
| `PATCH` | `/api/categories/{id}` | częściowa aktualizacja |
| `DELETE` | `/api/categories/{id}` | usunięcie |

Filtry: `?code=agd` (fragment), `?order[code]=desc`, `?order[createdAt]=asc`.

`code` jest **unikalny i maksymalnie 10-znakowy** — pilnowane przez walidator, przez unikalny indeks w bazie oraz przez sprawdzenie w Processorze, które przechwytuje też wyścig i zamienia go na `409`.

Kategorii przypisanej do produktów **nie da się usunąć** (`409`) — produkt musi mieć co najmniej jedną kategorię.

### Produkty — `/api/products`

| Metoda | Ścieżka | Opis |
|---|---|---|
| `GET` | `/api/products` | lista (paginacja po 30) |
| `GET` | `/api/products/{id}` | szczegóły |
| `POST` | `/api/products` | `{"name":…,"price":"1299.50","categoryIds":[1,2]}` |
| `PATCH` | `/api/products/{id}` | częściowa aktualizacja |
| `DELETE` | `/api/products/{id}` | usunięcie |

Filtry: `?name=pralka` (fragment), `?categories.code=AGD`, `?order[price]=asc`, `?price[gte]=1000`, `?createdAt[after]=2026-01-01`.

#### Współbieżność — dwie warstwy

Produkty **i kategorie** mają kolumnę `version` (`#[ORM\Version]`) i wystawiają ją w odpowiedzi. Kod kategorii jest unikalny i widoczny w reprezentacji każdego produktu, więc ciche nadpisanie cudzej zmiany nie jest tam neutralne. Chronią dwa różne okna:

| Okno | Mechanizm | Wynik konfliktu |
|---|---|---|
| wewnątrz jednego żądania (odczyt Providera → zapis Processora) | `#[ORM\Version]` | `409` |
| między żądaniami (GET → namysł użytkownika → PATCH) | `If-Match` | `412` |

Samo wersjonowanie serwerowe nie zamyka drugiego okna — klient musi mieć jak powiedzieć, **który stan** zamierza nadpisać:

```bash
ETAG=$(curl -sD- -o/dev/null -H "Authorization: Bearer $TOKEN" \
  http://localhost:8080/api/products/1 | grep -i ^etag | awk '{print $2}')
# → "1-1-146414f6"   (id-wersja-skrót osadzonych kategorii)

curl -X PATCH http://localhost:8080/api/products/1 \
  -H "Authorization: Bearer $TOKEN" -H "If-Match: $ETAG" \
  -H 'Content-Type: application/merge-patch+json' -d '{"name":"Nowa"}'
# 200 gdy nikt nie ubiegł, 412 gdy tak — dane nie zostają nadpisane
```

`If-Match: *` znaczy „byle istniał". Brak nagłówka = klient nie robi zapisów warunkowych; wersja serwerowa i tak pilnuje węższego okna.

ETagi są **wyliczane ze stanu**, nie z treści odpowiedzi — bo treści nie da się poznać przed przetworzeniem zapisu, a więc i porównać z `If-Match`. Szczegóły w sekcji o żądaniach warunkowych.

**`price` to string dziesiętny**, nie liczba JSON — patrz [Decyzje projektowe](#decyzje-projektowe). Wartość jest normalizowana do dwóch miejsc po przecinku (`"1299.5"` → `"1299.50"`).

### Log operacji — `/api/operation-logs`

Ślad audytowy zapisywany przez `OperationLogRecorder` w tej samej transakcji co produkt, **tylko do odczytu** (`ROLE_ADMIN`). Zapis przez API jest niemożliwy (`405`) — wpisy powstają wyłącznie razem z zapisem, który opisują.

| Metoda | Ścieżka | Opis |
|---|---|---|
| `GET` | `/api/operation-logs` | lista, od najnowszych |
| `GET` | `/api/operation-logs/{id}` | pojedynczy wpis |

Filtry: `?subjectType=Product`, `?subjectId=7`, `?action=created|updated|deleted`, `?channel=audit`, `?createdAt[after]=…`, `?order[createdAt]=asc`.

Dzięki temu efekt systemu powiadomień widać przez API, bez zaglądania do bazy:

```bash
curl -s -H "Authorization: Bearer $TOKEN" \
  "http://localhost:8080/api/operation-logs?subjectType=Product&subjectId=7" | jq
```

### Kody odpowiedzi

| Kod | Kiedy |
|---|---|
| `400` | payload, którego nie da się zdeserializować (np. `price` jako liczba) |
| `401` | brak lub nieważny token |
| `403` | zalogowany, ale bez `ROLE_ADMIN` |
| `404` | nieistniejący zasób lub nieistniejąca kategoria w `categoryIds` |
| `409` | duplikat kodu kategorii; kasowanie kategorii w użyciu; równoległa modyfikacja produktu w trakcie żądania |
| `412` | `If-Match` opisuje stan, którego produkt już nie ma |
| `422` | błąd walidacji (lista naruszeń z `propertyPath`) |
| `429` | przekroczony limit żądań |

Wszystkie błędy w formacie **RFC 7807 Problem Details**.

### Sonda zdrowia — `GET /health`

Poza `/api`, bez uwierzytelniania i bez serializera, bo orkiestrator odpytuje ją właśnie wtedy, gdy API nie działa. Zwraca `{"status":"ok"}` z kodem `200` albo `503`.

**Ma własny limiter** (120/min na IP). To, że sonda nie powinna dzielić limitu z API, nie znaczy, że powinna być bez limitu — jest nieuwierzytelniona, a każde wywołanie kosztuje round trip do MySQL-a i odczyt z Redisa. Przekroczenie → `429` z `Retry-After`, w JSON-ie, nie w HTML-u.

Treść odpowiedzi to jedno słowo. Ani która zależność padła, ani jej czas odpowiedzi nie trafiają do anonimowego klienta — idą do logu, tam gdzie jest operator.

### Żądania warunkowe i correlation ID

Pasujący `If-None-Match` zwraca **`304` bez treści** — dla pozycji i kolekcji, w obu formatach.

Wymagało to własnych walidatorów, bo domyślny ETag API Platform to skrót treści, a w JSON-LD treść zmienia się przy każdym żądaniu: każdemu zagnieżdżonemu obiektowi nadawany jest losowy blank-node `@id`. Ani `ApiProperty(genId: false)`, ani `normalizationContext['gen_id']` tego nie tłumią — `ItemNormalizer` ustawia flagę, zanim metadane właściwości dojdą do głosu.

| Zasób | Walidator |
|---|---|
| produkt (pozycja) | `id-wersja-skrót(kody kategorii)` — skrót łapie zmianę nazwy osadzonej kategorii |
| kategoria (pozycja) | `id-wersja` — kategoria nie osadza cudzych danych |
| kolekcja produktów | skrót z walidatorów pozycji **w kolejności wyników** + `totalItems` + URI żądania |
| log operacji, kolekcja kategorii | domyślny skrót treści API Platform (stabilny — brak zagnieżdżonych DTO) |

Walidator kolekcji pokrywa skład strony, nie tylko treść pozycji: wypadnięcie elementu zmienia dokument, choć pozostałe pozycje są nietknięte. Pokryte testami — dodanie produktu, usunięcie produktu i zmiana nazwy osadzonej kategorii unieważniają walidator kolekcji.

Każda odpowiedź niesie `X-Request-Id`, ten sam identyfikator trafia do każdej linii logu. Wartość przysłana przez klienta jest respektowana tylko po walidacji formatu — niesprawdzona byłaby wektorem log injection.

---

## Architektura powiadomień

To jest rdzeń zadania. Po zapisaniu produktu (utworzenie **i** aktualizacja) system rozsyła powiadomienie do **wszystkich zarejestrowanych kanałów**.

```
ProductProcessor
   └─ transakcja: persist + flush + OperationLogRecorder   ← produkt i audyt atomowo
   └─ commit
   └─ dispatch(ProductSavedEvent)                          ← dopiero potem powiadomienia
          │
          ▼
   ProductSavedNotificationListener
          │
          ▼
   NotificationDispatcher              ← nie zna żadnego kanału z nazwy
     ├──▶ PsrLoggerChannel  (priorytet 20)  → kanał monologa `notification`
     └──▶ EmailChannel      (priorytet  0)  → Mailpit / SMTP
```

### Dlaczego tak

**Kanały rejestrują się same.** Interfejs `NotificationChannelInterface` nosi `#[AutoconfigureTag('app.notification_channel')]`, a dispatcher pobiera je przez `#[AutowireIterator]`. W `services.yaml` **nie ma ani jednego wpisu** o kanałach:

```bash
docker exec zadanie-rekrutacyjne-php-1 php bin/console debug:container --tag=app.notification_channel
#  App\Notification\Channel\EmailChannel
#  App\Notification\Channel\PsrLoggerChannel
```

**Błąd jednego kanału nie dotyka pozostałych.** Powiadomienie jest efektem ubocznym zapisu, który już się powiódł — padnięty serwer SMTP nie może zamienić udanego `POST /api/products` w `500` ani zablokować pozostałych kanałów. Dispatcher opakowuje każdy `send()` w `try/catch (\Throwable)`, loguje błąd i zwraca `DispatchReport` z listą dostarczonych i nieudanych kanałów.

**Cena, którą płacimy świadomie:** skoro audyt dzieli transakcję z zapisem, awaria tabeli audytu wywraca zapis na `500`. Dostępność zapisów jest sprzężona z dostępnością audytu. To wybór fail-closed — dla śladu audytowego cicha luka jest gorsza niż odmowa — ale w systemie o wyższych wymaganiach dostępności trzeba by go odwrócić, płacąc za to prawdziwym outboxem z relayem.

**Ślad audytowy nie jest kanałem — i to jest celowe.** Kanał biegnie po zatwierdzeniu zapisu, więc jego INSERT byłby drugą transakcją: jej awaria zostawiłaby produkt bez śladu, jak powstał. Dla logu audytowego to najgorszy możliwy tryb awarii, więc `OperationLogRecorder` zapisuje wiersz **wewnątrz transakcji produktu**. To rozwiązuje połowę problemu outboxa bez żadnej nowej infrastruktury.

**Powiadomienia lecą po commicie.** Żaden mail nie wychodzi dla wycofanego produktu i żadne I/O sieciowe nie trzyma blokad bazy. `ProductSavedEvent` niesie gotowy obiekt wartości, nie encję — czyli dokładnie to, czego wymagałaby zamiana na wiadomość Messengera.

### Jak dodać kanał Slack (albo SMS)

**Jedna klasa. Zero zmian w istniejącym kodzie i konfiguracji.**

```php
<?php

declare(strict_types=1);

namespace App\Notification\Channel;

use App\Notification\Contract\NotificationChannelInterface;
use App\Notification\Contract\NotificationInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class SlackChannel implements NotificationChannelInterface
{
    public function __construct(private HttpClientInterface $http) {}

    public function getName(): string
    {
        return 'slack';
    }

    public function supports(NotificationInterface $notification): bool
    {
        return true; // albo np. tylko wybrane typy: $notification->getType() === 'product.changed'
    }

    public function send(NotificationInterface $notification): void
    {
        $this->http->request('POST', 'https://hooks.slack.com/services/…', [
            'json' => ['text' => $notification->getSubjectKey(), 'blocks' => $notification->getContext()],
        ]);
    }
}
```

Zapisanie pliku wystarczy — autowiring i tag zrobią resztę. Ten kontrakt jest **testowany**: `NotificationDispatcherTest::testAChannelTheDispatcherHasNeverSeenIsUsedWithoutAnyChange()` podstawia kanał, o którym dispatcher nie ma pojęcia, a `ChannelRegistrationTest` sprawdza mechanizm na realnym kontenerze.

### Nowy typ powiadomienia

Kanały nie są związane z produktami. Nowy typ = klasa implementująca `NotificationInterface` (dane) + zdarzenie i listener. Kanały `PsrLoggerChannel` i `EmailChannel` obsłużą go bez zmian — szablon maila renderuje to, co znajdzie w `getContext()`.

### Asynchroniczność

Wysyłka jest **synchroniczna** (Messenger jest poza zakresem zadania). Projekt jest na nią przygotowany: `ProductSavedEvent` wystarczy podmienić na message i przepiąć listener na handler — kanały pozostają nietknięte.

---

## Testy

```bash
make test
```

```
PHPUnit 12.5.35 — OK (90 tests, 395 assertions)
```

Każdy push uruchamia dokładnie to samo przez GitHub Actions ([.github/workflows/ci.yml](.github/workflows/ci.yml)) — jakość jest egzekwowana, nie deklarowana.

Testy działają na osobnej bazie `app_test`, każdy w transakcji wycofywanej po zakończeniu (`dama/doctrine-test-bundle`) — nie zależą od kolejności ani od stanu po poprzednim uruchomieniu.

```
tests/
├── Unit/          54 testy — dispatcher i kanały powiadomień, mappery,
│                  CategoryLinker, DecimalPrecisionValidator, PriceNormalizer
├── Integration/    2 testy — rejestracja kanałów na realnym kontenerze
├── Functional/    33 testy — CRUD, walidacja, autoryzacja, log operacji
└── Performance/    1 test  — strażnik regresji N+1 na kolekcji produktów
```

**Wymagane przez zadanie testy powiadomień** (`tests/Unit/Notification/`) pokrywają:

- rozesłanie do wszystkich wspierających kanałów i pominięcie tych, które nie wspierają,
- **izolację błędu** — kanał rzucający wyjątek nie przerywa pozostałych, jest logowany i widnieje w raporcie,
- kolejność kanałów, pusty zestaw kanałów,
- **rozszerzalność** — kanał nieznany dispatcherowi działa bez żadnej zmiany,
- budowę wiersza `OperationLog`, wpisu PSR-3 i wiadomości e-mail (adresat, klucz tematu, szablon, dane),
- zbudowanie powiadomienia z encji produktu i odmowę powiadamiania o nieutrwalonym produkcie.

## Jakość kodu

```bash
make check     # cs + phpstan + lint + testy
```

- **PHPStan level 8** na `src/` i `tests/`, z rozszerzeniami Symfony i Doctrine — zero błędów.
- **PHP-CS-Fixer**: `@Symfony` + `@PHP84Migration` + `declare_strict_types`.
- `lint:container`, `lint:yaml`, `lint:twig`, `doctrine:schema:validate`.

## Struktura projektu

```
src/
├── Category/      Entity · Repository · Dto · Mapper · Exception · State · Contract
├── Product/       j.w. + Service · Event · EventListener
├── Notification/  Contract · Channel · Entity · Mapper · State · Mail · Enum · Dispatcher
├── Security/      Entity (User, RefreshToken) · Repository · Command · UserChecker
└── Shared/        Entity/TimestampableTrait · Mail · Service · State · Validator · EventSubscriber
```

Zależności między modułami tworzą **graf acykliczny** — moduł ogólny nigdy nie wie o konkretnej domenie:

```
Product  → Category, Notification, Shared
Category → Shared          (pyta o użycie przez Category\Contract\CategoryUsageCheckerInterface)
Notification → Shared      (zna wyłącznie własne kontrakty — nie wie, czym jest Produkt)
Security → Shared
Shared   → (nic)
```

Zasady, których trzyma się kod:

- **Encja Doctrine nigdy nie jest zasobem API** — każdy zasób ma własne Output DTO z `#[ApiResource]`.
- **Każda operacja zapisu ma Input DTO** — osobny dla `create` i `update`; to whitelista pól, więc mass assignment jest niemożliwy.
- **Każdy zasób ma Provider i Processor** — brak polegania na domyślnym Doctrine.
- **Walidacja wyłącznie atrybutami** `Assert` na Input DTO.
- **Każda operacja ma `security:`** — żadna nie jest domyślnie otwarta.
- Wyjątki domenowe nie znają HTTP; mapowanie na kody jest w `api_platform.yaml`.

## Decyzje projektowe

**Symfony 8.1, nie 8.0.** Zadanie prosi o najnowszą stabilną wersję. Symfony 8.0 wypadło ze wsparcia bugfix — utrzymywane gałęzie to 6.4, 7.4 LTS i 8.1.

**API Platform `^4.3`, nie `4.x`.** Wersje 4.4 i 5.0 są w fazie alfa; luźniejszy constraint wpuściłby je do `composer.lock`.

**Cena jako `DECIMAL(12,2)` i string — również na wejściu API.** Pieniądze nigdy nie przechodzą przez `float`, nawet przejściowo: normalizacja `"1299.5"` → `"1299.50"` operuje na stringach. Konsekwencją jest kontrakt, w którym `price` to `"1299.50"`, a nie `1299.5`. Liczba JSON daje czytelne `400`, nie ciche zaokrąglenie. (Uboczna przyczyna: denormalizacja API Platform wymusza typ pola i odrzuca zarówno unię `string|int|float`, jak i `mixed`.)

**Własny constraint `DecimalPrecision`.** Żaden wbudowany nie wyraża „maksymalnie 2 miejsca po przecinku" bez arytmetyki zmiennoprzecinkowej: `Assert\Regex` odrzuca nie-stringi, a `Assert\DivisibleBy` liczyłby na floatach — czyli dokładnie tam, gdzie `DECIMAL` ma chronić.

**`markUpdated()` w `TimestampableTrait`.** Doctrine nie uznaje zmiany kolekcji ManyToMany za zmianę encji właścicielskiej, więc `#[ORM\PreUpdate]` **nie odpala się**, gdy w produkcie zmieniają się wyłącznie kategorie. Jawne dotknięcie pola naprawia i znacznik czasu, i sam fakt wykonania `UPDATE`. Pokryte testem funkcjonalnym.

**Nieznane `categoryIds` → 404, nie ciche pominięcie.** Inaczej produkt mógłby po cichu dostać mniej kategorii, niż prosił klient — albo żadnej.

**`int` jako identyfikator.** Dane nie są przypisane do użytkownika ani wrażliwe na enumerację. Dla zasobów per-użytkownik właściwszy byłby UUIDv7.

**PHPUnit 12, nie 13.** `dama/doctrine-test-bundle` deklaruje wsparcie do 12.3. Wersja PHPUnit nie jest przedmiotem zadania, stabilność izolacji transakcyjnej testów — jest.

**Mailpit zamiast `null://null`.** Zadanie dopuszcza fikcyjnego maila, ale `null://` znaczy tyle, że nie widać nic. Mailpit realnie dostarcza wiadomość pod http://localhost:8025 i nie wypuszcza jej z maszyny — ta sama fikcyjność, ale z dowodem. W testach transport to `null://null`.

### Co świadomie pominąłem

- **Messenger / kolejki** — poza zakresem. Rozwiązałyby drugą połowę problemu trwałości (gwarantowane dostarczenie powiadomień); pierwsza połowa — atomowość śladu audytowego — jest już rozwiązana bez nich. Ścieżka migracji opisana wyżej.
- **Rejestracja użytkowników przez API** — zadanie dotyczy produktów i kategorii; konta zakłada komenda CLI.
