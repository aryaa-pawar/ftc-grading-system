-- ==========================================
-- FTC GRADING SYSTEM - DATABASE VIEWS
-- ==========================================
-- Dialect: MySQL / MariaDB
-- ==========================================

USE ftc_grading;


-- ==========================================
-- VIEW 1: SUBJECT ASSESSMENT SCORES
-- ==========================================
-- Averages marks from multiple faculties for
-- a student, subject and assessment type.
-- The averaged mark is rounded to the nearest whole mark.
-- ==========================================

CREATE OR REPLACE VIEW v_subject_assessment_scores AS
SELECT 
    fme.student_id,
    fme.resolved_paper_id AS paper_id,
    fme.subject_id,
    fme.assessment_type_id,

    CASE 
        WHEN COUNT(CASE WHEN fme.is_absent = 0 AND fme.marks_obtained IS NOT NULL THEN 1 END) > 0 
        THEN AVG(CASE WHEN fme.is_absent = 0 THEN fme.marks_obtained ELSE NULL END)
        ELSE 0
    END AS subject_score,

    MAX(
        CASE fme.assessment_type_id
            WHEN 1 THEN COALESCE(NULLIF(psm.subject_max_internal, 0), sfm.max_marks)
            WHEN 2 THEN COALESCE(NULLIF(psm.subject_max_midterm, 0), sfm.max_marks)
            WHEN 3 THEN COALESCE(NULLIF(psm.subject_max_final, 0), sfm.max_marks)
            ELSE sfm.max_marks
        END
    ) AS subject_max_marks

FROM (
    SELECT
        f.*,
        COALESCE(f.paper_id, (
            SELECT MIN(psm0.paper_id)
            FROM paper_subject_mapping psm0
            WHERE psm0.batch_id = std0.batch_id
              AND psm0.subject_id = f.subject_id
        )) AS resolved_paper_id
    FROM faculty_marks_entry f
    JOIN students std0 ON std0.student_id = f.student_id
) fme

JOIN subject_faculty_mapping sfm 
    ON fme.subject_id = sfm.subject_id 
   AND fme.faculty_id = sfm.faculty_id

JOIN students std 
    ON fme.student_id = std.student_id 
   AND std.batch_id = sfm.batch_id

LEFT JOIN paper_subject_mapping psm
    ON psm.paper_id = fme.resolved_paper_id
   AND psm.subject_id = fme.subject_id
   AND psm.batch_id = std.batch_id

GROUP BY 
    fme.student_id,
    fme.resolved_paper_id,
    fme.subject_id,
    fme.assessment_type_id;


-- ==========================================
-- VIEW 2: PAPER ASSESSMENT SCORES
-- ==========================================
-- Converts subject-level scores to paper-level
-- scores using the paper weightage.
--
-- Internal  = 30
-- Mid-Term  = 25
-- Final     = 45
-- ==========================================

CREATE OR REPLACE VIEW v_paper_assessment_scores AS
SELECT 
    sas.student_id,
    psm.paper_id,
    sas.assessment_type_id,

    ROUND(
        SUM(sas.subject_score)
        /
        NULLIF(
            SUM(
                CASE sas.assessment_type_id
                    WHEN 1 THEN COALESCE(NULLIF(psm.subject_max_internal, 0), sas.subject_max_marks)
                    WHEN 2 THEN COALESCE(NULLIF(psm.subject_max_midterm, 0), sas.subject_max_marks)
                    WHEN 3 THEN COALESCE(NULLIF(psm.subject_max_final, 0), sas.subject_max_marks)
                    ELSE sas.subject_max_marks
                END
            ),
            0
        )
        *
        CASE
            WHEN sas.assessment_type_id = 1 THEN p.max_internal
            WHEN sas.assessment_type_id = 2 THEN p.max_midterm
            WHEN sas.assessment_type_id = 3 THEN p.max_final
        END,
        0
    ) AS paper_score,

    MAX(
        CASE 
            WHEN sas.subject_score IS NULL THEN 1
            ELSE 0
        END
    ) AS was_absent

FROM v_subject_assessment_scores sas

JOIN paper_subject_mapping psm 
    ON sas.subject_id = psm.subject_id
   AND sas.paper_id = psm.paper_id

JOIN papers p 
    ON psm.paper_id = p.paper_id

JOIN students std 
    ON sas.student_id = std.student_id 
   AND psm.batch_id = std.batch_id

GROUP BY 
    sas.student_id,
    sas.paper_id,
    psm.paper_id,
    sas.assessment_type_id;


-- ==========================================
-- VIEW 3: INTERNAL MARKS CONVERTER
-- ==========================================
-- Converts raw internal subject marks into
-- their weighted internal score.
-- ==========================================

CREATE OR REPLACE VIEW v_internal_marks_converter AS
SELECT 
    student_id,
    paper_id,
    subject_id,

    CASE 
        WHEN was_absent = 1 THEN NULL
        ELSE subject_score
    END AS converted_score

FROM
(
    SELECT 
        sas.student_id,
        psm.paper_id,
        sas.subject_id,

        ROUND(
            (sas.subject_score / sas.subject_max_marks)
            *
            (
                p.max_internal
                *
                psm.subject_weightage_pct
                / 100.0
            ),
            0
        ) AS subject_score,

        MAX(
            CASE 
                WHEN sas.subject_score IS NULL THEN 1
                ELSE 0
            END
        ) AS was_absent

    FROM v_subject_assessment_scores sas

    JOIN paper_subject_mapping psm 
        ON sas.subject_id = psm.subject_id
       AND sas.paper_id = psm.paper_id

    JOIN papers p 
        ON psm.paper_id = p.paper_id

    JOIN students std 
        ON sas.student_id = std.student_id 
       AND std.batch_id = psm.batch_id

    WHERE sas.assessment_type_id = 1

    GROUP BY
        sas.student_id,
        sas.paper_id,
        psm.paper_id,
        sas.subject_id,
        sas.subject_score,
        sas.subject_max_marks,
        p.max_internal,
        psm.subject_weightage_pct

) sub;


-- ==========================================
-- VIEW 4: TABULATION
-- ==========================================
-- Combines:
--
-- Internal Score
-- Mid-Term Score
-- Final Exam Score
--
-- into the final combined paper total.
-- ==========================================

CREATE OR REPLACE VIEW v_tabulation AS
    WITH ElectivePaperTotals AS
    (
        SELECT
            st.student_id,
            psm.paper_id,
            SUM(COALESCE(sas.subject_score, 0)) AS raw_obtained,
            SUM(
                CASE sas.assessment_type_id
                    WHEN 1 THEN COALESCE(NULLIF(psm.subject_max_internal, 0), p.max_internal * psm.subject_weightage_pct / 100.0)
                    WHEN 2 THEN COALESCE(NULLIF(psm.subject_max_midterm, 0), p.max_midterm * psm.subject_weightage_pct / 100.0)
                    WHEN 3 THEN COALESCE(NULLIF(psm.subject_max_final, 0), p.max_final * psm.subject_weightage_pct / 100.0)
                    ELSE 0
                END
            ) AS raw_maximum,
            MAX(CASE WHEN sas.subject_score IS NULL THEN 1 ELSE 0 END) AS was_absent
        FROM students st
        INNER JOIN paper_subject_mapping psm
            ON psm.batch_id = st.batch_id
        INNER JOIN papers p
            ON p.paper_id = psm.paper_id
           AND (
               p.is_elective = 1
               OR UPPER(p.paper_code) IN ('P5', 'P-V')
               OR UPPER(p.paper_name) LIKE '%ELECTIVE%'
           )
        INNER JOIN elective_subject_mapping esm
            ON esm.subject_id = psm.subject_id
           AND LOWER(TRIM(esm.group_code)) = LOWER(TRIM(st.group_code))
        LEFT JOIN v_subject_assessment_scores sas
            ON sas.student_id = st.student_id
           AND sas.subject_id = psm.subject_id
           AND sas.paper_id = psm.paper_id
           AND sas.assessment_type_id IS NOT NULL
        GROUP BY
            st.student_id,
            psm.paper_id
    ),
    PaperScores AS
    (
        SELECT
            vt.*,
            vt.combined_total AS normalized_combined_total
        FROM
        (
            SELECT
                s.student_id,
                s.roll_no,
                s.student_name,
                p.paper_id,
                p.paper_name,
                CASE WHEN v_int.was_absent = 1 THEN NULL ELSE COALESCE(v_int.paper_score, 0) END AS internal_score,
                CASE WHEN v_mid.was_absent = 1 THEN NULL ELSE COALESCE(v_mid.paper_score, 0) END AS midterm_score,
                CASE WHEN v_fin.was_absent = 1 THEN NULL ELSE COALESCE(v_fin.paper_score, 0) END AS final_exam_score,
                CASE
                    WHEN v_int.was_absent = 1
                      OR v_mid.was_absent = 1
                      OR v_fin.was_absent = 1
                    THEN NULL
                    ELSE COALESCE(v_int.paper_score, 0)
                       + COALESCE(v_mid.paper_score, 0)
                       + COALESCE(v_fin.paper_score, 0)
                END AS combined_total
            FROM students s
            JOIN (
                SELECT DISTINCT paper_id, batch_id
                FROM paper_subject_mapping
            ) batch_papers
                ON batch_papers.batch_id = s.batch_id
            JOIN papers p
                ON p.paper_id = batch_papers.paper_id
            LEFT JOIN v_paper_assessment_scores v_int
                ON s.student_id = v_int.student_id
               AND p.paper_id = v_int.paper_id
               AND v_int.assessment_type_id = 1
            LEFT JOIN v_paper_assessment_scores v_mid
                ON s.student_id = v_mid.student_id
               AND p.paper_id = v_mid.paper_id
               AND v_mid.assessment_type_id = 2
            LEFT JOIN v_paper_assessment_scores v_fin
                ON s.student_id = v_fin.student_id
               AND p.paper_id = v_fin.paper_id
               AND v_fin.assessment_type_id = 3
        ) vt
        JOIN papers p
            ON p.paper_id = vt.paper_id
        LEFT JOIN ElectivePaperTotals ept
            ON ept.student_id = vt.student_id
           AND ept.paper_id = vt.paper_id
    )
SELECT
    student_id,
        roll_no,
        student_name,
        paper_id,
        paper_name,
        internal_score,
        midterm_score,
        final_exam_score,
        normalized_combined_total AS combined_total
FROM PaperScores;


-- ==========================================
-- VIEW 5: RESULT REGISTER
-- ==========================================

CREATE OR REPLACE VIEW v_result_register AS
WITH StudentPaperTotals AS
(
    SELECT
        student_id,
        SUM(combined_total) AS grand_total,
        SUM(
            p.max_internal
            + p.max_midterm
            + p.max_final
        ) AS max_possible_marks
    FROM v_tabulation vt
    JOIN papers p
        ON vt.paper_id = p.paper_id
    GROUP BY student_id
)
SELECT
    s.student_id,
    s.roll_no,
    s.student_name,
    s.designation,
    s.rmc,
    spt.grand_total,
    spt.max_possible_marks,
    ROUND(
        (spt.grand_total / spt.max_possible_marks) * 100,
        2
    ) AS percentage,
    CASE
        WHEN ROUND((spt.grand_total / spt.max_possible_marks) * 100, 2) >= 90.00 THEN 'Outstanding'
        WHEN ROUND((spt.grand_total / spt.max_possible_marks) * 100, 2) >= 80.00 THEN 'Excellent'
        WHEN ROUND((spt.grand_total / spt.max_possible_marks) * 100, 2) >= 70.00 THEN 'Very good'
        WHEN ROUND((spt.grand_total / spt.max_possible_marks) * 100, 2) >= 60.00 THEN 'Good'
        WHEN ROUND((spt.grand_total / spt.max_possible_marks) * 100, 2) >= 50.00 THEN 'Passed'
        ELSE 'Failed'
    END AS remarks,
    s.custom_remarks
FROM students s
JOIN StudentPaperTotals spt
    ON s.student_id = spt.student_id;
