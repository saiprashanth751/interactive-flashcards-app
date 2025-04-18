<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

include '../includes/db.php';
$user_id = $_SESSION['user_id'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Custom Study</title>
    <link href="../src/output.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-image: url('https://www.transparenttextures.com/patterns/subtle-grey.png');
            background-repeat: repeat;
            background-size: 200px 200px;
        }
        .btn-primary {
            @apply bg-gradient-to-r from-purple-600 to-indigo-600 text-white px-4 py-2 rounded hover:from-purple-700 hover:to-indigo-700 transition duration-300;
        }

        /* Input Text Color Fix */
        input[type="text"],
        input[type="number"],
        input[type="email"],
        input[type="password"],
        textarea,
        select {
            color: #374151 !important;
        }

        input::placeholder {
            color: #9CA3AF !important;
            opacity: 1 !important;
        }

        /* Focus styles for better accessibility */
        input:focus, textarea:focus, select:focus {
            outline: 2px solid #8B5CF6;
            outline-offset: 2px;
        }
    </style>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.11.5/gsap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.11.5/ScrollTrigger.min.js"></script>
</head>
<body class="bg-gray-50 min-h-screen">
    <!-- Header -->
    <header class="bg-gradient-to-r from-purple-700 to-indigo-700 text-white p-6 shadow-lg">
        <div class="max-w-4xl mx-auto flex justify-between items-center">
            <h1 class="text-3xl font-bold tracking-wide">Create Custom Study Session</h1>
            <a href="index.php" class="text-sm hover:underline transition duration-300">Back to Dashboard</a>
        </div>
    </header>

    <!-- Main Content -->
    <main class="p-6">
        <div class="max-w-4xl mx-auto">
            <div class="bg-white p-8 rounded-lg shadow-md hover:shadow-lg transition-shadow duration-300 animated-section">
                <h2 class="text-2xl font-semibold text-gray-800 mb-6 flex items-center">
                    <i class="fas fa-clock mr-2 text-purple-600"></i> Set Up Your Study Session
                </h2>
                <form action="start_custom_study.php" method="POST" class="space-y-6">
                    <div class="flex flex-col space-y-2">
                        <label for="session_name" class="text-sm font-medium text-gray-700 flex items-center">
                            <i class="fas fa-tag mr-2 text-gray-500"></i> Session Name
                        </label>
                        <input type="text" id="session_name" name="session_name" class="w-full px-4 py-3 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-purple-500 transition duration-300" placeholder="e.g., Morning Review" required>
                    </div>
                    <div class="flex flex-col space-y-2">
                        <label for="time_limit" class="text-sm font-medium text-gray-700 flex items-center">
                            <i class="fas fa-hourglass-start mr-2 text-gray-500"></i> Time Limit (minutes)
                        </label>
                        <input type="number" id="time_limit" name="time_limit" min="1" class="w-full px-4 py-3 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-purple-500 transition duration-300" placeholder="e.g., 30" required>
                    </div>
                    <div class="flex justify-end">
                        <button type="submit" name="proceed_to_select_decks" class="btn-primary flex items-center">
                            <i class="fas fa-arrow-right mr-2"></i> Next: Select Decks
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <!-- GSAP Animations -->
    <script>
        gsap.registerPlugin(ScrollTrigger);

        gsap.utils.toArray('.animated-section').forEach(section => {
            gsap.from(section, {
                scrollTrigger: {
                    trigger: section,
                    start: "top 80%",
                    toggleActions: "play none none none",
                    once: true
                },
                duration: 1,
                y: 50,
                opacity: 0,
                ease: "power2.out",
                immediateRender: false
            });
        });

        gsap.from('button', {
            scrollTrigger: {
                trigger: 'button',
                start: "top 90%",
                toggleActions: "play none none none",
                once: true
            },
            duration: 0.8,
            scale: 0,
            opacity: 0,
            ease: "back.out(1.7)",
            immediateRender: false
        });
    </script>
</body>
</html>