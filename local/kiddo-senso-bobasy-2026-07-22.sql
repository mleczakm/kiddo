-- Senso Bobasy 22.07.2026 12:00-13:30 (karol115)
-- Termin już istnieje jako cancelled w starej serii weekly — reaktywacja + przeniesienie do letniej serii one_time.

BEGIN;

-- Istniejący rekord: 019e5eb1-d2dd-aaf0-c6e8-6da16fea7d1f (2026-07-22 12:00, cancelled)
-- Letnia seria Senso Bobasy: 019f31ef-fbaf-77f1-16bf-b724a576d3ec
UPDATE lesson
SET
  status = 'active',
  capacity = 8,
  series_id = '019f31ef-fbaf-77f1-16bf-b724a576d3ec'
WHERE id = '019e5eb1-d2dd-aaf0-c6e8-6da16fea7d1f'
  AND schedule = '2026-07-22 12:00:00'
  AND title = 'Senso Bobasy';

-- Weryfikacja (oczekiwany 1 wiersz: active, capacity 8, one_time)
SELECT l.id, l.schedule, l.title, l.status, l.capacity, s.type, s.status AS series_status
FROM lesson l
JOIN series s ON s.id = l.series_id
WHERE l.id = '019e5eb1-d2dd-aaf0-c6e8-6da16fea7d1f';

COMMIT;
-- W razie problemów: ROLLBACK;
