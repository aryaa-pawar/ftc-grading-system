<?php

require_once __DIR__ . '/../../includes/auth_guard.php';

http_response_code(410);
header('Location: index.php?error=central-delete-only');
exit;


/*
|--------------------------------------------------------------------------
| GET FACULTY ID
|--------------------------------------------------------------------------
*/

$facultyId = isset($_GET["id"])
    ? (int) $_GET["id"]
    : 0;


/*
|--------------------------------------------------------------------------
| VALIDATE ID
|--------------------------------------------------------------------------
*/

if ($facultyId <= 0) {

    header("Location: index.php?error=invalid");

    exit;

}


/*
|--------------------------------------------------------------------------
| SOFT DELETE FACULTY
|--------------------------------------------------------------------------
*/

try {

    $stmt = $pdo->prepare("
        UPDATE faculties
        SET is_deleted = 1
        WHERE faculty_id = :faculty_id
          AND is_deleted = 0
    ");


    $stmt->execute([

        ":faculty_id" => $facultyId

    ]);


    /*
    |--------------------------------------------------------------------------
    | CHECK WHETHER UPDATE HAPPENED
    |--------------------------------------------------------------------------
    */

    if ($stmt->rowCount() > 0) {

        header(
            "Location: index.php?success=deactivated"
        );

        exit;

    }


    /*
    |--------------------------------------------------------------------------
    | FACULTY NOT FOUND / ALREADY DELETED
    |--------------------------------------------------------------------------
    */

    header(
        "Location: index.php?error=notfound"
    );

    exit;


} catch (PDOException $e) {

    die(
        "Unable to deactivate faculty: "
        . htmlspecialchars(
            $e->getMessage()
        )
    );

}
