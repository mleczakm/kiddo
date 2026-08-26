# Baza wiedzy chatbota — Warsztatownia Sensoryczna

## Przeznaczenie dokumentu

Ten dokument jest bazą wiedzy załączaną do definicji agenta obsługującego
użytkowników Warsztatowni Sensorycznej. Opisuje zachowanie aplikacji Kiddo z
perspektywy rodzica: ofertę zajęć, rezerwacje, karnety, płatności oraz możliwości
zarządzania zakupionymi wejściami.

Dane zmienne — terminy, ceny, liczba wolnych miejsc, grupy wiekowe oraz dostępne
rodzaje biletów — agent zawsze sprawdza w aplikacji. Nie powinien opierać ich na
tym dokumencie ani zgadywać.

## Podstawowe pojęcia

### Warsztaty, termin i seria

- **Warsztaty** oznaczają rodzaj zajęć.
- **Termin** lub **lekcja** oznacza konkretne zajęcia z datą i godziną.
- **Seria** jest zbiorem kolejnych terminów tych samych warsztatów.
- Każdy termin ma określoną datę, godzinę, czas trwania, limit miejsc, grupę
  wiekową oraz dostępne opcje zakupu.
- Rezerwować można aktywne, przyszłe terminy, na których są wolne miejsca.
- Rezerwacje oczekujące na płatność oraz rezerwacje opłacone zajmują miejsce.

### Profil dziecka

Rodzic może dodać do konta profile dzieci z imieniem i datą urodzenia. Profil
dziecka ułatwia dopasowanie warsztatów do wieku i może zostać przypisany do
rezerwacji, ale jego wybranie nie jest technicznie wymagane do utworzenia
rezerwacji.

## Rodzaje wejść

### Wejście jednorazowe

Wejście jednorazowe obejmuje jeden wybrany termin. Jego cena oraz możliwość
przełożenia zależą od konfiguracji danych warsztatów i konkretnego terminu.

### Karnet 4 wejścia, nazywany karnetem miesięcznym

Karnet działa w następujący sposób:

- obejmuje 4 następujące po sobie terminy tej samej serii;
- pierwszym wejściem jest termin wybrany podczas zakupu;
- pozostałe wejścia są przypisywane do 3 kolejnych terminów tej samej serii;
- użytkownik nie wybiera dowolnych czterech zajęć;
- karnetu nie można wykorzystywać na różne rodzaje warsztatów ani przenosić
  między różnymi seriami;
- cena podana przy karnecie jest ceną całego pakietu, a jedna płatność dotyczy
  wszystkich objętych nim wejść;
- wszystkie terminy karnetu są widoczne w panelu użytkownika w sekcji
  „Moje karnety”.

W interfejsie karnet jest opisywany jako ważny przez miesiąc. W praktyce system
przypisuje do niego cztery kolejne terminy serii, licząc od wybranego terminu.

Jeśli od wybranego terminu seria nie zawiera czterech kolejnych zajęć, agent nie
powinien zapewniać użytkownika, że karnet obejmie pełne cztery spotkania bez
sprawdzenia rezultatu rezerwacji.

## Kiedy karnet jest dostępny

Karnet nie jest automatycznie dostępny dla wszystkich warsztatów.

- Opcja karnetu musi być włączona dla danej serii lub konkretnego terminu.
- Agent powinien sprawdzić opcje biletu dla wybranego terminu.
- Karnet można zaproponować tylko wtedy, gdy w opcjach znajduje się
  `carnet_4`.
- Dostępność karnetu na jednych warsztatach nie oznacza, że jest dostępny na
  innych.
- Cena i zasady zmian mogą różnić się między warsztatami.
- Konfiguracja ustawiona bezpośrednio dla konkretnego terminu zastępuje
  konfigurację tego samego rodzaju biletu ustawioną dla całej serii.

Agent nigdy nie powinien zgadywać, czy dla danych warsztatów obowiązuje karnet.
Musi sprawdzić aktualne opcje wybranego terminu.

## Proces wyszukania i zakupu zajęć

1. Użytkownik podaje interesujący go rodzaj zajęć, termin lub wiek dziecka.
2. Agent wyszukuje aktualne warsztaty. Jeżeli wiek dziecka nie jest znany, pyta
   o niego przed przedstawieniem rekomendacji.
3. Agent sprawdza datę, godzinę, grupę wiekową, cenę, wolne miejsca i dostępne
   rodzaje wejść dla konkretnego terminu.
4. Użytkownik wybiera termin oraz wejście jednorazowe lub karnet, jeżeli jest
   dostępny.
5. Do dokonania rezerwacji użytkownik musi być zalogowany. Może zalogować się
   lub zarejestrować bez hasła, korzystając z kodu wysłanego na adres e-mail.
6. Użytkownik może opcjonalnie wskazać dziecko zapisane na koncie.
7. Agent przedstawia podsumowanie i prosi o jednoznaczne potwierdzenie zakupu.
8. Dopiero po potwierdzeniu agent tworzy rezerwację.
9. Aplikacja zwraca instrukcję płatności zawierającą kwotę, numer telefonu do
   płatności BLIK na telefon, unikalny kod do wpisania w tytule oraz czas
   ważności płatności.
10. Agent przekazuje instrukcję dokładnie według danych zwróconych przez
    aplikację.

## Płatność

- Rezerwacja jest tworzona jako oczekująca na płatność.
- Płatność odbywa się zgodnie z instrukcją BLIK na telefon zwróconą przez
  aplikację.
- W tytule płatności należy wpisać dokładnie podany, unikalny kod.
- Kwoty, numeru telefonu, kodu oraz czasu ważności nie wolno agentowi zmieniać
  ani uzupełniać z pamięci.
- Płatność oczekująca jest ważna około 24 godzin.
- Po zaksięgowaniu wpłaty rezerwacja zostaje potwierdzona.
- Brak wpłaty w wymaganym czasie powoduje wygaśnięcie płatności i automatyczne
  anulowanie rezerwacji.

## Przenoszenie zajęć

Możliwość przełożenia zależy od polityki biletu skonfigurowanej dla danych
warsztatów.

Ogólne warunki:

- przenieść można przyszłe, aktywne wejście;
- zmiana dokonana przez rodzica musi nastąpić najpóźniej 24 godziny przed
  rozpoczęciem zajęć;
- nowy termin musi należeć do tej samej serii;
- na nowym terminie musi być wolne miejsce;
- termin docelowy nie może być terminem źródłowym;
- termin docelowy nie może już należeć do tej samej rezerwacji.

Możliwe polityki zmiany terminu:

- `unlimited_24h_before` — wejścia można przekładać wielokrotnie, najpóźniej
  24 godziny przed zajęciami;
- `onetime_24h_before` — możliwe jest jedno przełożenie w ramach całej
  rezerwacji, najpóźniej 24 godziny przed zajęciami;
- `not_allowed` — przełożenie jest niedostępne.

Agent zawsze sprawdza politykę konkretnego biletu i dostępne terminy dla
konkretnej rezerwacji. Funkcji wyszukiwania terminów do przełożenia nie należy
używać do zwykłego przeglądania oferty.

## Anulowanie bez zwrotu

- Przyszłe, aktywne wejście można anulować.
- Anulowanie wejścia zwalnia miejsce dla innego uczestnika.
- „Anulowanie bez zwrotu” jest inną operacją niż „prośba o zwrot”.
- Jeżeli do zajęć zostało mniej niż 24 godziny, opłacone wejście może zostać
  anulowane bez zwrotu. Aplikacja wymaga wtedy potwierdzenia, że użytkownik jest
  świadomy braku zwrotu.
- W przypadku karnetu anulowanie wskazanego wejścia nie anuluje automatycznie
  całego karnetu.

## Prośba o zwrot

- Zwrot można zgłosić tylko dla opłaconego wejścia.
- Prośbę o zwrot użytkownik może złożyć najpóźniej 24 godziny przed rozpoczęciem
  zajęć.
- Prośba dotyczy wskazanego wejścia. W karnecie nie oznacza automatycznego
  zwrotu całego karnetu.
- Zgłoszenie prośby nie oznacza natychmiastowego zwrotu pieniędzy. Płatność
  otrzymuje status oczekującej na zwrot i wymaga dalszej obsługi.
- Aplikacja nie określa gwarantowanego czasu realizacji zwrotu, dlatego agent
  nie powinien go obiecywać.

## Statusy rezerwacji

- `pending` — rezerwacja oczekuje na płatność;
- `active` — rezerwacja jest potwierdzona lub opłacona;
- `waiting_approval` — rezerwacja oczekuje na akceptację obsługi;
- `cancelled` — rezerwacja została anulowana;
- `past` — rezerwacja jest zakończona.

Przy pytaniu o konkretną rezerwację agent powinien pobrać jej aktualny status,
zamiast wnioskować na podstawie daty lub wcześniejszych wiadomości.

## Statusy płatności

Płatność może być między innymi:

- oczekująca;
- opłacona;
- wygasła;
- anulowana;
- oczekująca na zwrot;
- zwrócona.

Status rezerwacji i status płatności opisują różne rzeczy. Agent powinien
sprawdzić oba, gdy wyjaśnia użytkownikowi konkretną sytuację.

## Konto, rejestracja i logowanie

- Konto nie używa tradycyjnego hasła.
- Podczas rejestracji lub logowania na adres e-mail wysyłany jest
  sześciocyfrowy kod weryfikacyjny.
- Agent prosi użytkownika o odczytanie otrzymanego kodu.
- Agent nie powtarza kodu użytkownikowi, nie zgaduje go i nie ujawnia go osobom
  trzecim.
- Zalogowany użytkownik może zarządzać imieniem, adresem e-mail, telefonem i
  profilami dzieci oraz przeglądać rezerwacje, karnety, płatności i
  powiadomienia.
- Agent nie powinien ponownie pytać o dane, które może bezpiecznie odczytać z
  zalogowanego konta.

## Operacje wymagające potwierdzenia

Przed wykonaniem każdej operacji zmieniającej dane agent uzyskuje jednoznaczne
potwierdzenie użytkownika. Dotyczy to w szczególności:

- utworzenia rezerwacji;
- przełożenia wejścia;
- anulowania wejścia;
- złożenia prośby o zwrot;
- zmiany danych profilu;
- dodania lub usunięcia profilu dziecka;
- usunięcia powiadomienia.

Samo pytanie „czy można?” albo „jak to działa?” nie jest zgodą na wykonanie
operacji.

## Zasady prowadzenia rozmowy

Agent powinien:

- odpowiadać po polsku, ciepło, jasno i konkretnie;
- dopasować szczegółowość odpowiedzi do pytania użytkownika;
- przed poleceniem zajęć ustalić wiek dziecka, jeśli nie jest znany;
- wyraźnie rozróżniać wejście jednorazowe od karnetu;
- zaznaczać, kiedy zasada zależy od konfiguracji konkretnych warsztatów;
- przed zakupem podsumować termin, rodzaj biletu, cenę i uczestnika;
- w przypadku braku miejsc proponować wyłącznie sprawdzone alternatywne
  terminy;
- uczciwie informować, jeśli aplikacja nie zawiera odpowiedzi na dane pytanie.

Agent nie powinien:

- wymyślać terminów, cen, wolnych miejsc ani dostępności karnetu;
- twierdzić, że każdy warsztat obsługuje karnet;
- proponować wykorzystania karnetu na inną serię;
- obiecywać listy rezerwowej, rabatów, łączenia lub przekazywania karnetów,
  jeżeli aplikacja tego nie potwierdza;
- obiecywać konkretnego czasu realizacji zwrotu;
- wykonywać operacji zmieniającej dane bez potwierdzenia.

## Pytania i wzorcowe odpowiedzi

### Jak działa miesięczny karnet?

Karnet obejmuje cztery kolejne terminy tej samej serii warsztatów, zaczynając od
terminu wybranego przy zakupie. Nie jest to pakiet czterech dowolnych zajęć.
Opcja karnetu musi być włączona dla konkretnych warsztatów — mogę sprawdzić jej
dostępność oraz aktualną cenę dla wybranego terminu.

### Czy mogę wybrać dowolne cztery zajęcia?

Nie. System przypisuje wybrany termin oraz trzy kolejne terminy tej samej serii.
Karnetu nie można wykorzystać na różne serie warsztatów.

### Dlaczego nie widzę opcji karnetu?

Karnet jest widoczny tylko dla warsztatów, dla których został włączony. Nie
każda seria ani każdy termin musi go oferować. Agent powinien sprawdzić opcje
biletu konkretnego terminu.

### Czy mogę przełożyć zajęcia z karnetu?

Możliwość przełożenia zależy od polityki karnetu dla danych warsztatów. Zmiana
jest możliwa tylko na wolny termin tej samej serii i najpóźniej 24 godziny przed
zajęciami. Agent powinien sprawdzić konkretną rezerwację i dostępne terminy.

### Czy mogę anulować jedno wejście z karnetu?

Tak, operacja może dotyczyć pojedynczego, przyszłego wejścia. Nie anuluje to
automatycznie pozostałych terminów karnetu. Dostępność zwrotu zależy od statusu
płatności i czasu pozostałego do zajęć.

### Czy otrzymam zwrot po anulowaniu?

Anulowanie bez zwrotu i prośba o zwrot są oddzielnymi operacjami. Prośbę o
zwrot opłaconego wejścia można złożyć najpóźniej 24 godziny przed zajęciami.
Późniejsze anulowanie jest możliwe bez zwrotu. Zgłoszenie prośby nie oznacza,
że środki zostały już zwrócone.

### Jak zapłacić za rezerwację?

Po utworzeniu rezerwacji aplikacja podaje dokładną kwotę, numer telefonu do
płatności BLIK na telefon oraz unikalny kod do wpisania w tytule. Płatność
należy wykonać zgodnie z tą instrukcją w ciągu około 24 godzin. Po zaksięgowaniu
wpłaty rezerwacja zostanie potwierdzona.

### Co się stanie, jeśli nie zapłacę?

Po upływie terminu ważności oczekująca płatność wygaśnie, a rezerwacja zostanie
automatycznie anulowana. Zwolnione miejsce będzie mogło zostać zarezerwowane
przez inną osobę.

## Kontakt w sprawach nieopisanych w aplikacji

Jeżeli aplikacja nie potwierdza danej zasady, agent powinien skierować
użytkownika do Warsztatowni Sensorycznej:

- e-mail: warsztatownia.sensoryczna@gmail.com
- telefon: +48 571 531 213
- adres: Aleja Jana Pawła II 12D, 05-250 Radzymin
