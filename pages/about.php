<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us</title>
    <link href="../src/output.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body class="min-h-screen bg-gray-50 font-poppins">
    <!-- Header -->
    <header class="bg-gradient-to-r from-purple-700 to-indigo-700 text-white p-6 shadow-lg">
        <div class="max-w-6xl mx-auto flex justify-between items-center">
            <h1 class="text-3xl font-bold tracking-wide">Flashcard Project</h1>
            <div class="flex items-center space-x-6">
                <a href="index.php" class="text-sm text-white hover:underline transition duration-300">Dashboard</a>
                <a href="about.php" class="text-sm text-white hover:underline transition duration-300">About Us</a>
                <!-- <a href="contact.php" class="text-sm text-white hover:underline transition duration-300">Contact</a> -->
                <a href="profile.php" class="text-sm text-white hover:underline transition duration-300">Profile</a>
                <a href="logout.php" class="text-sm text-white hover:underline transition duration-300">Logout</a>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="p-6">
        <div class="max-w-6xl mx-auto bg-white p-6 rounded-lg shadow-md">
            <h1 class="text-3xl font-bold mb-6 text-gray-800">About Us</h1>
            
            <div class="mb-8">
                <p class="text-gray-600 mb-4">The Flashcard Project is an academic tool designed to help students and learners enhance their knowledge retention through interactive flashcards. Our platform allows users to create, study, and share decks tailored to their learning needs.</p>
                <p class="text-gray-600 mb-4">Our goal is to make studying engaging and effective. Whether you're preparing for exams, learning a new language, or mastering a subject, our system supports you with features like study goals, daily challenges, and custom sessions.</p>
                <p class="text-gray-600 mb-4">Created for academic purposes, this project aims to empower learners worldwide. Join us and take your learning to the next level!</p>
            </div>

            <!-- Development Team Section -->
            <h2 class="text-2xl font-bold mb-8 text-center text-gray-800">Meet Our Team</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
                <!-- Team Member Card 1 -->
                <div class="bg-white p-6 rounded-xl shadow-lg hover:shadow-xl transition-shadow duration-300">
                    <div class="w-24 h-24 mx-auto mb-4 rounded-full bg-purple-100 flex items-center justify-center">
                        <i class="fas fa-user-tie text-3xl text-purple-600"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-center text-gray-800">Sai Prashanth</h3>
                    <p class="text-center text-purple-600 mb-2">Lead Developer</p>
                    <p class="text-sm text-gray-600 text-center">Full-Stack Developer</p>
                </div>

                <!-- Team Member Card 2 -->
                <div class="bg-white p-6 rounded-xl shadow-lg hover:shadow-xl transition-shadow duration-300">
                    <div class="w-24 h-24 mx-auto mb-4 rounded-full bg-blue-100 flex items-center justify-center">
                        <i class="fas fa-code text-3xl text-blue-600"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-center text-gray-800">Laxmikanth</h3>
                    <p class="text-center text-blue-600 mb-2">Frontend Specialist</p>
                    <p class="text-sm text-gray-600 text-center">UI/UX designer & interaction expert</p>
                </div>

                <!-- Team Member Card 3 -->
                <div class="bg-white p-6 rounded-xl shadow-lg hover:shadow-xl transition-shadow duration-300">
                    <div class="w-24 h-24 mx-auto mb-4 rounded-full bg-green-100 flex items-center justify-center">
                        <i class="fas fa-database text-3xl text-green-600"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-center text-gray-800">Shivansh Mishra</h3>
                    <p class="text-center text-green-600 mb-2">Backend Engineer</p>
                    <p class="text-sm text-gray-600 text-center">Database architect & API developer</p>
                </div>

                <!-- Team Member Card 4 -->
                <div class="bg-white p-6 rounded-xl shadow-lg hover:shadow-xl transition-shadow duration-300">
                    <div class="w-24 h-24 mx-auto mb-4 rounded-full bg-orange-100 flex items-center justify-center">
                        <i class="fas fa-mobile-alt text-3xl text-orange-600"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-center text-gray-800">Ankit Kumar Singh</h3>
                    <p class="text-center text-orange-600 mb-2">UI/UX designer</p>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-gray-800 text-white p-4 mt-6">
        <div class="max-w-6xl mx-auto flex flex-col sm:flex-row justify-between items-center">
            <p class="text-sm">© 2023 Flashcard Project. All rights reserved.</p>
            <div class="mt-2 sm:mt-0 space-x-4">
                <a href="about.php" class="text-sm text-white hover:underline transition duration-300">About Us</a>
                <!-- <a href="contact.php" class="text-sm text-white hover:underline transition duration-300">Contact</a> -->
            </div>
        </div>
    </footer>
</body>
</html>