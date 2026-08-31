<?php
/*
|--------------------------------------------------------------------------
| FTC Grading & Tabulation Management System
| Common Header
|--------------------------------------------------------------------------
*/
?>

<!DOCTYPE html>
<html lang="en">
<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>
        <?php
        echo isset($pageTitle)
            ? htmlspecialchars($pageTitle)
            : "FTC Grading & Tabulation Management System";
        ?>
    </title>

    <style>

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            background-color: #f4f6f8;
            color: #222;
            min-height: 100vh;
            overflow-x: hidden;
        }

        a {
            text-decoration: none;
            color: inherit;
        }

        button,
        input,
        select,
        textarea {
            font-family: inherit;
        }

        .page-container {
            min-height: 100vh;
            max-width: 100%;
            overflow-x: hidden;
        }

        .page-container > div {
            min-width: 0;
        }

        .page-container main {
            min-width: 0;
            overflow-x: auto;
        }

        .main-content {
            padding: 25px;
        }

        .page-title {
            margin-bottom: 20px;
        }

        .page-title h1 {
            font-size: 26px;
            margin-bottom: 5px;
        }

        .page-title p {
            color: #666;
            font-size: 14px;
        }

        .card {
            background: #ffffff;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

        .btn {
            display: inline-block;
            padding: 9px 16px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
        }

        .btn-primary {
            background-color: #1f4e79;
            color: #ffffff;
        }

        .btn-primary:hover {
            background-color: #163a5c;
        }

        .btn-danger {
            background-color: #c0392b;
            color: #ffffff;
        }

        .btn-success {
            background-color: #278a4b;
            color: #ffffff;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e5e5e5;
        }

        th {
            background-color: #f1f3f5;
            font-weight: 600;
        }

        tr:hover {
            background-color: #fafafa;
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 14px;
        }

        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }

        .message {
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 15px;
        }

        .message-success {
            background-color: #e8f5e9;
            color: #216e39;
        }

        .message-error {
            background-color: #fdecea;
            color: #a12622;
        }

    </style>

</head>

<body>

<div class="page-container">