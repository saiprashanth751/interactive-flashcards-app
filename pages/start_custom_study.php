<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

include '../includes/db.php';
$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['proceed_to_select_decks'])) {
        // From custom_study_sessions.php: Save session details and redirect to select decks
        $session_name = $_POST['session_name'] ?? '';
        $time_limit = $_POST['time_limit'] ?? 0;

        if ($session_name && $time_limit > 0) {
            $stmt = $pdo->prepare("INSERT INTO custom_study_sessions (user_id, name, time_limit, status) VALUES (?, ?, ?, 'active')");
            $stmt->execute([$user_id, $session_name, $time_limit]);
            $session_id = $pdo->lastInsertId();

            // Store the time limit in the session to persist the timer
            $_SESSION['timer'][$session_id] = [
                'time_limit' => $time_limit * 60, // Convert to seconds
                'start_time' => time(), // Store the start time
            ];

            header("Location: select_decks_for_session.php?session_id=$session_id");
            exit();
        } else {
            header("Location: custom_study_sessions.php?error=invalid_input");
            exit();
        }
    } 
    elseif (isset($_POST['start_session'])) {
        // From select_decks_for_session.php: Start the session with selected decks
        $session_id = $_POST['session_id'] ?? 0;
        $selected_decks = $_POST['decks'] ?? [];

        if ($session_id <= 0 || empty($selected_decks)) {
            header("Location: select_decks_for_session.php?session_id=$session_id&error=no_decks_selected");
            exit();
        }

        try {
            // Insert selected decks into custom_study_session_decks
            $stmt = $pdo->prepare("INSERT INTO custom_study_session_decks (session_id, deck_id, status) VALUES (?, ?, 'pending')");
            foreach ($selected_decks as $deck_id) {
                $stmt->execute([$session_id, $deck_id]);
            }

            // Insert flashcards from selected decks into custom_study_session_flashcards
            foreach ($selected_decks as $deck_id) {
                $stmt = $pdo->prepare("
                    INSERT INTO custom_study_session_flashcards (session_id, flashcard_id, status)
                    SELECT ?, id, 'pending' FROM flashcards WHERE deck_id = ?
                ");
                $stmt->execute([$session_id, $deck_id]);
            }

            header("Location: study_custom_session.php?session_id=$session_id");
            exit();
        } catch (PDOException $e) {
            error_log("Error in start_custom_study.php: " . $e->getMessage());
            header("Location: select_decks_for_session.php?session_id=$session_id&error=database_error");
            exit();
        }
    }
}

header("Location: custom_study_sessions.php?error=invalid_request");
exit();
?>