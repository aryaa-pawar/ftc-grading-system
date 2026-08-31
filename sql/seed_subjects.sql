-- FTC Grading subject catalog seed data.
-- Safe to run repeatedly: subject_code is unique and existing rows are updated.
USE ftc_grading;

INSERT INTO subjects (subject_code, subject_name, is_elective)
VALUES
    ('P1-DYN', 'Dynamic Meteorology', 0),
    ('P1-NWP', 'Numerical Weather Prediction', 0),
    ('P2-PHY', 'Physical Meteorology', 0),
    ('P3-SYN', 'Synoptic Meteorology', 0),
    ('P3-AVN', 'Aviation Meteorology', 0),
    ('P4-CLM', 'Climate Science', 0),
    ('P4-HYD', 'Hydrometeorology', 0),
    ('P5-STA', 'Statistics', 0),
    ('P5-PHY-OCEAN', 'Physical Oceanography', 1),
    ('P5-SAT', 'Satellite Meteorology', 1),
    ('P5-RADAR', 'Radar Meteorology', 1),
    ('P5-COMP', 'Computer Applications', 1)
ON DUPLICATE KEY UPDATE
    subject_name = VALUES(subject_name),
    is_elective = VALUES(is_elective);

-- Verify the seeded catalog.
SELECT subject_id, subject_code, subject_name, is_elective
FROM subjects
WHERE subject_code IN (
    'P1-DYN', 'P1-NWP', 'P2-PHY', 'P3-SYN', 'P3-AVN',
    'P4-CLM', 'P4-HYD', 'P5-STA', 'P5-PHY-OCEAN', 'P5-SAT',
    'P5-RADAR', 'P5-COMP'
)
ORDER BY subject_id ASC;
