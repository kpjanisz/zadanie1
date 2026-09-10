#!/usr/bin/env bash
#
# Jednorazowy onboarding przy tworzeniu codespace'a.
#
# Robi dokładnie to, co README każe zrobić ręcznie — i celowo przez cele
# Makefile, a nie przez skopiowane polecenia, żeby nie powstało drugie źródło
# prawdy, które po cichu rozjedzie się z pierwszym.
#
# Idempotentny: `up` i `migrate` nic nie zmieniają na gotowym stacku, a `jwt`
# ma `--skip-if-exists`. Jedyny wyjątek to `fixtures`, które czyszczą tabele —
# dlatego uruchamiane tylko wtedy, gdy baza jest jeszcze pusta.

set -euo pipefail

cd "$(dirname "$0")/.."

step() { printf '\n\033[36m▸ %s\033[0m\n' "$1"; }

# Cykl życia devcontainera potrafi wystartować przed demonem Dockera, a wtedy
# pierwsze `docker compose` pada na gnieździe, którego jeszcze nie ma.
step 'Czekam na demona Dockera'
for attempt in $(seq 1 60); do
    if docker info >/dev/null 2>&1; then
        break
    fi

    if [ "$attempt" -eq 60 ]; then
        echo 'Docker nie wystartował w ciągu dwóch minut — przerywam.' >&2
        exit 1
    fi

    sleep 2
done

step 'Budowanie obrazów i start kontenerów (to trwa najdłużej)'
make up

step 'Instalacja zależności PHP'
make install

step 'Generowanie par kluczy JWT (dev i test)'
make jwt

step 'Migracje — schemat bazy'
make migrate

# doctrine:fixtures:load czyści tabele przed załadowaniem, więc na ponownym
# uruchomieniu skasowałoby dane, które ktoś zdążył wprowadzić.
step 'Dane demonstracyjne'
if [ "$(docker compose exec -T php php bin/console dbal:run-sql \
        'SELECT COUNT(*) FROM product' 2>/dev/null | grep -cE '^ +[1-9]')" -gt 0 ]; then
    echo 'Baza zawiera już produkty — pomijam, żeby niczego nie skasować.'
else
    make fixtures
fi

cat <<'KONIEC'

────────────────────────────────────────────────────────────────────
  Gotowe.

  Otwórz zakładkę PORTS i kliknij adres przy porcie 8080, a potem
  dopisz /console.html — to najszybszy sposób, żeby przejrzeć całość.

    /console.html   konsola do klikania (logowanie, CRUD, log operacji)
    /api/docs       OpenAPI / Swagger UI
    /api            samo API
    port 8025       Mailpit — tu ląduje mail po zapisie produktu

  Konta z fixtures (hasło: DevPassw0rd!)
    admin@example.test    ROLE_ADMIN — może zapisywać
    viewer@example.test   tylko odczyt; przy zapisie dostanie 403

  W terminalu adresy localhost działają normalnie, więc demo curl-em
  z README przechodzi bez żadnych zmian.

    make test     pakiet testów
    make check    pełna bramka jakości (styl, PHPStan, linty, testy)
    make help     pozostałe polecenia
────────────────────────────────────────────────────────────────────

KONIEC
