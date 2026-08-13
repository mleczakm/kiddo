-- Letnie zajęcia kiddo 2026 (karol115)
-- Uruchomić w transakcji; zweryfikować SELECT przed COMMIT.

BEGIN;

-- 1a. Nowe serie one_time
INSERT INTO series (id, type, status, ticket_options) VALUES
  ('019f31ef-fbaf-77f1-16bf-b724a576d3eb', 'one_time', 'active',
   '[{"type":{"#type":"App\\Entity\\TicketType","#scalar":"one_time"},"#type":"App\\Entity\\TicketOption","price":{"#type":"Brick\\Money\\Money","amount":"60.00","currency":"PLN"},"description":"Bilet jednorazowy","reschedulePolicy":{"#type":"App\\Entity\\TicketReschedulePolicy","#scalar":"onetime_24h_before"}}]'::jsonb),
  ('019f31ef-fbaf-77f1-16bf-b724a576d3ec', 'one_time', 'active',
   '[{"type":{"#type":"App\\Entity\\TicketType","#scalar":"one_time"},"#type":"App\\Entity\\TicketOption","price":{"#type":"Brick\\Money\\Money","amount":"60.00","currency":"PLN"},"description":"Bilet jednorazowy","reschedulePolicy":{"#type":"App\\Entity\\TicketReschedulePolicy","#scalar":"onetime_24h_before"}}]'::jsonb),
  ('019f31ef-fbaf-77f1-16bf-b724a576d3ed', 'one_time', 'active',
   '[{"type":{"#type":"App\\Entity\\TicketType","#scalar":"one_time"},"#type":"App\\Entity\\TicketOption","price":{"#type":"Brick\\Money\\Money","amount":"60.00","currency":"PLN"},"description":"Bilet jednorazowy","reschedulePolicy":{"#type":"App\\Entity\\TicketReschedulePolicy","#scalar":"onetime_24h_before"}}]'::jsonb);

-- 1b. Reaktywacja anulowanych + przeniesienie do nowych serii
UPDATE lesson SET status = 'active', capacity = 8, series_id = '019f31ef-fbaf-77f1-16bf-b724a576d3eb'
WHERE id IN (
  '019e2da8-3eca-442b-8d0d-aefb63dcb37d',  -- 15.07 Bałaganki
  '019ec300-a909-0404-318a-32a998dad1a3'   -- 12.08 Bałaganki
);

UPDATE lesson SET status = 'active', capacity = 8, series_id = '019f31ef-fbaf-77f1-16bf-b724a576d3ec'
WHERE id IN (
  '019e099b-be01-d0ac-5a17-ecb98254dffe',  -- 08.07 Senso Bobasy
  '019ec300-a915-6497-0a3c-18204471b6a2',  -- 12.08 Senso Bobasy
  '019f05f3-51eb-8152-3153-bc716348d80a'   -- 26.08 Senso Bobasy
);

UPDATE lesson SET status = 'active', capacity = 5, series_id = '019f31ef-fbaf-77f1-16bf-b724a576d3ed'
WHERE id = '019defdb-ec8f-572f-9746-82655c70c56f';  -- 03.07 Ćwiczymy mózg

-- 1c. Przeniesienie aktywnych 01.07 do nowych serii (bookings zostają)
UPDATE lesson SET series_id = '019f31ef-fbaf-77f1-16bf-b724a576d3eb'
WHERE id = '019de58f-346e-0832-1fce-1b7650298321';

UPDATE lesson SET series_id = '019f31ef-fbaf-77f1-16bf-b724a576d3ec'
WHERE id = '019de58f-3473-63ee-2bc2-8b11b6350940';

-- 1d. INSERT brakujących lekcji (kopiowanie metadanych z szablonów)

-- Bałaganki 09.07 16:30
INSERT INTO lesson (
  id, series_id, status, ticket_options,
  title, lead, visual_theme, description,
  capacity, schedule, duration, category, age_range_min, age_range_max
)
SELECT
  '019f31ef-fbaf-77f1-16bf-b724a576d3ee'::uuid,
  '019f31ef-fbaf-77f1-16bf-b724a576d3eb'::uuid,
  'active',
  '[]'::jsonb,
  title, lead, visual_theme, description,
  8,
  '2026-07-09 16:30:00'::timestamp,
  duration, category, age_range_min, age_range_max
FROM lesson
WHERE id = '019de58f-346e-0832-1fce-1b7650298321';

-- Ćwiczymy mózg 13.07 10:00
INSERT INTO lesson (
  id, series_id, status, ticket_options,
  title, lead, visual_theme, description,
  capacity, schedule, duration, category, age_range_min, age_range_max
)
SELECT
  '019f31ef-fbaf-77f1-16bf-b724a576d3ef'::uuid,
  '019f31ef-fbaf-77f1-16bf-b724a576d3ed'::uuid,
  'active',
  '[]'::jsonb,
  title, lead, visual_theme, description,
  5,
  '2026-07-13 10:00:00'::timestamp,
  duration, category, age_range_min, age_range_max
FROM lesson
WHERE id = '019defdb-ec8f-572f-9746-82655c70c56f';

-- Ćwiczymy mózg 11.08 10:00
INSERT INTO lesson (
  id, series_id, status, ticket_options,
  title, lead, visual_theme, description,
  capacity, schedule, duration, category, age_range_min, age_range_max
)
SELECT
  '019f31ef-fbaf-77f1-16bf-b724a576d3f0'::uuid,
  '019f31ef-fbaf-77f1-16bf-b724a576d3ed'::uuid,
  'active',
  '[]'::jsonb,
  title, lead, visual_theme, description,
  5,
  '2026-08-11 10:00:00'::timestamp,
  duration, category, age_range_min, age_range_max
FROM lesson
WHERE id = '019defdb-ec8f-572f-9746-82655c70c56f';

-- Ćwiczymy mózg 20.08 16:30
INSERT INTO lesson (
  id, series_id, status, ticket_options,
  title, lead, visual_theme, description,
  capacity, schedule, duration, category, age_range_min, age_range_max
)
SELECT
  '019f31ef-fbaf-77f1-16bf-b724a576d3f1'::uuid,
  '019f31ef-fbaf-77f1-16bf-b724a576d3ed'::uuid,
  'active',
  '[]'::jsonb,
  title, lead, visual_theme, description,
  5,
  '2026-08-20 16:30:00'::timestamp,
  duration, category, age_range_min, age_range_max
FROM lesson
WHERE id = '019defdb-ec8f-572f-9746-82655c70c56f';

-- Bałaganki 27.08 10:00
INSERT INTO lesson (
  id, series_id, status, ticket_options,
  title, lead, visual_theme, description,
  capacity, schedule, duration, category, age_range_min, age_range_max
)
SELECT
  '019f31ef-fbaf-77f1-16bf-b724a576d3f2'::uuid,
  '019f31ef-fbaf-77f1-16bf-b724a576d3eb'::uuid,
  'active',
  '[]'::jsonb,
  title, lead, visual_theme, description,
  8,
  '2026-08-27 10:00:00'::timestamp,
  duration, category, age_range_min, age_range_max
FROM lesson
WHERE id = '019de58f-346e-0832-1fce-1b7650298321';

-- 1e. Weryfikacja przed COMMIT
SELECT l.schedule, l.title, l.status, l.capacity, s.type, s.status AS series_status
FROM lesson l
JOIN series s ON s.id = l.series_id
WHERE (l.schedule, l.title) IN (
  ('2026-07-01 10:00:00', 'Bałaganki'),
  ('2026-07-01 12:00:00', 'Senso Bobasy'),
  ('2026-07-03 10:00:00', 'Ćwiczymy mózg przez ruch - grupa I'),
  ('2026-07-08 12:00:00', 'Senso Bobasy'),
  ('2026-07-09 16:30:00', 'Bałaganki'),
  ('2026-07-13 10:00:00', 'Ćwiczymy mózg przez ruch - grupa I'),
  ('2026-07-15 10:00:00', 'Bałaganki'),
  ('2026-08-11 10:00:00', 'Ćwiczymy mózg przez ruch - grupa I'),
  ('2026-08-12 10:00:00', 'Bałaganki'),
  ('2026-08-12 12:00:00', 'Senso Bobasy'),
  ('2026-08-20 16:30:00', 'Ćwiczymy mózg przez ruch - grupa I'),
  ('2026-08-26 12:00:00', 'Senso Bobasy'),
  ('2026-08-27 10:00:00', 'Bałaganki')
)
ORDER BY l.schedule, l.title;

COMMIT;
