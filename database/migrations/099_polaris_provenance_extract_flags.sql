-- Polaris: model↔source provenance links + extraction feature flags (off by default).
-- Draft-first brochure/text extraction only; paid AI remains gated separately.

CREATE TABLE IF NOT EXISTS polaris_model_sources (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    model_id INT UNSIGNED NOT NULL,
    source_id INT UNSIGNED NOT NULL,
    is_primary TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_polaris_model_source (model_id, source_id),
    KEY idx_polaris_ms_source (source_id),
    CONSTRAINT fk_polaris_ms_model FOREIGN KEY (model_id) REFERENCES polaris_rv_models (id) ON DELETE CASCADE,
    CONSTRAINT fk_polaris_ms_source FOREIGN KEY (source_id) REFERENCES polaris_data_sources (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO feature_flags (flag_key, is_enabled, description, updated_at)
VALUES
    ('polaris_brochure_extract', 0, 'Polaris deterministic brochure/text → import drafts (never auto-publish).', NOW()),
    ('polaris_ai_import', 0, 'Polaris paid AI brochure extraction via Assist AI orchestrator (planned; keep OFF).', NOW())
ON DUPLICATE KEY UPDATE description = VALUES(description);

-- Link demo Southern Cross model to the demo brochure source when both exist.
INSERT INTO polaris_model_sources (model_id, source_id, is_primary, created_at)
SELECT m.id, s.id, 1, NOW()
FROM polaris_rv_models m
INNER JOIN polaris_data_sources s ON s.brand_id = m.brand_id AND s.title = 'Demo Horizon 2026 brochure (fixture)'
WHERE m.slug = 'southern-cross' AND m.brand_id = 5 AND m.is_demo = 1
ON DUPLICATE KEY UPDATE is_primary = VALUES(is_primary);
