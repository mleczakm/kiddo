-- Insert robots.txt setting
INSERT INTO setting (id, "key", content)
VALUES (
    '550e8400-e29b-41d4-a716-446655440001',
    'static_endpoint_robots.txt',
    '{"content_type": "text/plain", "body": "User-agent: *\nDisallow: /admin/\nDisallow: /login_check\nDisallow: /_wdt\nDisallow: /_profiler\nDisallow: /_components\nDisallow: /_error\nAllow: /\n\nSitemap: /sitemap.xml"}'::jsonb
);

-- Insert sitemap.xml setting
INSERT INTO setting (id, "key", content)
VALUES (
    '550e8400-e29b-41d4-a716-446655440002',
    'static_endpoint_sitemap.xml',
    '{"content_type": "application/xml", "body": "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n  <url>\n    <loc>/</loc>\n    <lastmod>2024-06-04</lastmod>\n    <changefreq>daily</changefreq>\n    <priority>1.0</priority>\n  </url>\n  <url>\n    <loc>/panel</loc>\n    <lastmod>2024-06-04</lastmod>\n    <changefreq>daily</changefreq>\n    <priority>0.9</priority>\n  </url>\n  <url>\n    <loc>/dashboard</loc>\n    <lastmod>2024-06-04</lastmod>\n    <changefreq>daily</changefreq>\n    <priority>0.9</priority>\n  </url>\n  <url>\n    <loc>/profil</loc>\n    <lastmod>2024-06-04</lastmod>\n    <changefreq>weekly</changefreq>\n    <priority>0.8</priority>\n  </url>\n  <url>\n    <loc>/profile</loc>\n    <lastmod>2024-06-04</lastmod>\n    <changefreq>weekly</changefreq>\n    <priority>0.8</priority>\n  </url>\n  <url>\n    <loc>/warsztaty</loc>\n    <lastmod>2024-06-04</lastmod>\n    <changefreq>weekly</changefreq>\n    <priority>0.7</priority>\n  </url>\n  <url>\n    <loc>/workshops</loc>\n    <lastmod>2024-06-04</lastmod>\n    <changefreq>weekly</changefreq>\n    <priority>0.7</priority>\n  </url>\n  <url>\n    <loc>/register</loc>\n    <lastmod>2024-06-04</lastmod>\n    <changefreq>monthly</changefreq>\n    <priority>0.5</priority>\n  </url>\n</urlset>"}'::jsonb
);

-- Insert security.txt setting (optional, for security best practices)
INSERT INTO setting (id, "key", content)
VALUES (
    '550e8400-e29b-41d4-a716-446655440003',
    'static_endpoint_security.txt',
    '{"content_type": "text/plain", "body": "Contact: mailto:security@warsztatowniasensoryczna.pl\nExpires: 2025-06-04T00:00:00.000Z\nPreferred-Languages: en, pl\nCanonical: /security.txt"}'::jsonb
);
