# Bank mail Worker

Transport HMAC i kontrola rozmiaru pochodzą ze wspólnego źródła
`infrastructure-mikrus/integrations/bank-mail-worker`. Kopie w `shared/` są
wersjonowane, żeby repozytorium działało samodzielnie w CI i przy wdrożeniu.
Aktualizacja: uruchom w repozytorium infrastruktury
`node integrations/bank-mail-worker/sync.mjs --write --test --project kiddo`.
Sprawdzenie wszystkich kopii: ten sam skrypt z `--check`.

`npm test` sprawdza dotychczasowe zachowanie i wspólny kontrakt w rzeczywistym
workerd. Nagłówki `X-Kiddo-Bank-*`, znacznik czasu w sekundach, prefiks podpisu
`v1=` i format payloadu Kiddo pozostają zgodne z jego odbiornikiem. Transport
nie podąża za przekierowaniami; błędy wracają do kolejki.
