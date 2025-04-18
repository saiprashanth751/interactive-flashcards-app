<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    // No redirect so visitors can view landing page
}
?>

<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FlashMaster - Learn Smarter, Faster</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="../src/output.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@600;800&family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/aos@next/dist/aos.css" />
    <style>
        :root {
            --primary: #6B46C1;
            --secondary: #38B2AC;
            --accent: #F56565;
            --background: #F9FAFB;
        }
        body {
            background: var(--background);
        }
        .frosted-glass {
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            background: rgba(255, 255, 255, 0.15);
        }
        .gradient-bg {
            background: linear-gradient(135deg, #5A3DA6 0%, #2D928D 100%);
        }
        .feature-card {
            transition: transform 0.3s ease;
        }
        .feature-card:hover {
            transform: translateY(-8px);
        }
        .navbar-logo:hover {
            transform: scale(1.05);
        }
        .btn-primary {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .btn-primary:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }
        .particle-bg::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(circle at 30% 30%, rgba(255, 255, 255, 0.2), transparent);
            animation: particleMove 10s infinite ease-in-out;
            z-index: -1;
        }
        @keyframes particleMove {
            0%, 100% { transform: translate(0, 0); }
            50% { transform: translate(20px, -20px); }
        }
        .back-to-top {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: var(--primary);
            color: white;
            padding: 10px;
            border-radius: 50%;
            display: none;
        }
        .back-to-top.show {
            display: block;
        }
        .avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 18px;
        }
        section, nav, footer {
            visibility: visible !important;
            opacity: 1 !important;
        }
        .navbar-logo span {
            color: white;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.3);
        }
        .dark .navbar-logo span {
            color: #E5E7EB;
        }
        .cta-heading {
            color: black;
            /* color: var(--background); */
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.4);
        }
    </style>
</head>
<body class="min-h-screen font-poppins transition-colors duration-300 dark:bg-gray-900">
    <!-- Navigation -->
    <nav class="fixed w-full z-50 frosted-glass dark:bg-gray-800/70 shadow-sm" aria-label="Main navigation">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-14">
                <div class="flex items-center">
                    <a href="/" class="navbar-logo transition-transform">
                        <span class="text-xl font-bold font-inter">
                            FlashMaster
                        </span>
                    </a>
                </div>
                <div class="hidden md:flex items-center space-x-4">
                    <a href="#features" class="text-gray-600 dark:text-gray-200 hover:text-[var(--primary)] transition-colors text-sm">Features</a>
                    <a href="#testimonials" class="text-gray-600 dark:text-gray-200 hover:text-[var(--primary)] transition-colors text-sm">Testimonials</a>
                    <a href="login.php" class="btn-primary px-4 py-1.5 rounded-full bg-[var(--primary)] text-white text-sm hover:bg-[var(--secondary)] transition-colors">
                        Get Started
                    </a>
                    <button class="theme-toggle text-gray-600 dark:text-gray-200 p-2 rounded-full hover:bg-gray-200 dark:hover:bg-gray-700" aria-label="Toggle theme">
                        <i class="fas fa-moon text-sm"></i>
                    </button>
                </div>
                <div class="md:hidden">
                    <button class="mobile-menu-toggle p-2" aria-label="Toggle mobile menu">
                        <i class="fas fa-bars text-gray-600 dark:text-gray-200"></i>
                    </button>
                </div>
            </div>
            <div class="mobile-menu hidden md:hidden bg-white dark:bg-gray-800 px-4 py-2">
                <a href="#features" class="block text-gray-600 dark:text-gray-200 hover:text-[var(--primary)] py-2">Features</a>
                <a href="#testimonials" class="block text-gray-600 dark:text-gray-200 hover:text-[var(--primary)] py-2">Testimonials</a>
                <a href="login.php" class="block btn-primary px-4 py-2 rounded-full bg-[var(--primary)] text-white text-center">Get Started</a>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="pt-32 pb-20 gradient-bg text-white relative particle-bg">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center" data-aos="fade-up">
                <h1 class="text-4xl md:text-5xl font-bold font-inter mb-6">
                    Learn Smarter with
                    <span class="bg-clip-text text-transparent bg-gradient-to-r from-white to-gray-200">
                        FlashMaster
                    </span>
                </h1>
                <p class="text-lg md:text-xl text-gray-100 mb-8 max-w-2xl mx-auto">
                    AI-powered flashcards to master any subject, anytime, anywhere.
                </p>
                <div class="flex flex-col sm:flex-row gap-4 justify-center">
                    <a href="register.php" class="btn-primary px-4 py-2 rounded-full bg-white text-[var(--primary)] text-sm hover:bg-gray-100">
                        Start Free
                    </a>
                    <a href="#features" class="btn-primary px-4 py-2 rounded-full bg-transparent border border-white text-white text-sm hover:bg-white hover:text-[var(--primary)]">
                        Explore Features
                    </a>
                </div>
            </div>
            <div class="mt-16 max-w-4xl mx-auto" data-aos="zoom-in" data-aos-delay="200">
                <img src="../assets/Screenshot 2025-04-15 113536.png" alt="FlashMaster App Preview" class="rounded-xl shadow-lg border border-white/10" loading="lazy" onerror="this.src='https://via.placeholder.com/800x450?text=FlashMaster+Preview';">
            </div>
        </div>
    </section>

    <!-- Key Features -->
    <section id="features" class="py-20 bg-white dark:bg-gray-800">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16" data-aos="fade-up">
                <h2 class="text-3xl md:text-4xl font-bold font-inter mb-6 dark:text-white">
                    Tools for Success
                </h2>
                <p class="text-base text-gray-600 dark:text-gray-300 max-w-lg mx-auto">
                    Study smarter with features designed to boost retention and motivation.
                </p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <div class="feature-card bg-white dark:bg-gray-700 p-6 rounded-xl shadow-md flex flex-col items-center text-center" data-aos="fade-up" data-aos-delay="100">
                    <div class="w-12 h-12 mb-4 bg-[var(--primary)]/10 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-[var(--primary)]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path>
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold mb-2 dark:text-white">Personal Dashboard</h3>
                    <p class="text-gray-600 dark:text-gray-300 text-sm">Your study hub with tailored insights and quick access to decks.</p>
                </div>
                <div class="feature-card bg-white dark:bg-gray-700 p-6 rounded-xl shadow-md flex flex-col items-center text-center" data-aos="fade-up" data-aos-delay="200">
                    <div class="w-12 h-12 mb-4 bg-[var(--secondary)]/10 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-[var(--secondary)]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold mb-2 dark:text-white">Study Progress</h3>
                    <p class="text-gray-600 dark:text-gray-300 text-sm">Track growth with progress bars and streaks.</p>
                </div>
                <div class="feature-card bg-white dark:bg-gray-700 p-6 rounded-xl shadow-md flex flex-col items-center text-center" data-aos="fade-up" data-aos-delay="300">
                    <div class="w-12 h-12 mb-4 bg-[var(--accent)]/10 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-[var(--accent)]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"></path>
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold mb-2 dark:text-white">Custom Study Sessions</h3>
                    <p class="text-gray-600 dark:text-gray-300 text-sm">Tailor sessions to your learning style.</p>
                </div>
                <div class="feature-card bg-white dark:bg-gray-700 p-6 rounded-xl shadow-md flex flex-col items-center text-center" data-aos="fade-up" data-aos-delay="400">
                    <div class="w-12 h-12 mb-4 bg-[var(--primary)]/10 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-[var(--primary)]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold mb-2 dark:text-white">Deck Management</h3>
                    <p class="text-gray-600 dark:text-gray-300 text-sm">Organize and share decks easily.</p>
                </div>
                <div class="feature-card bg-white dark:bg-gray-700 p-6 rounded-xl shadow-md flex flex-col items-center text-center" data-aos="fade-up" data-aos-delay="500">
                    <div class="w-12 h-12 mb-4 bg-[var(--secondary)]/10 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-[var(--secondary)]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold mb-2 dark:text-white">Gamification</h3>
                    <p class="text-gray-600 dark:text-gray-300 text-sm">Earn badges and compete with friends.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Testimonials Section -->
    <section id="testimonials" class="py-20 bg-gray-100 dark:bg-gray-900">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16" data-aos="fade-up">
                <h2 class="text-3xl md:text-4xl font-bold font-inter mb-6 dark:text-white">
                    What Students Say
                </h2>
                <p class="text-base text-gray-600 dark:text-gray-300 max-w-lg mx-auto">
                    Join thousands who love studying with FlashMaster.
                </p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="bg-white dark:bg-gray-700 p-6 rounded-xl shadow-md" data-aos="fade-up" data-aos-delay="100">
                    <p class="text-gray-600 dark:text-gray-300 text-sm mb-4">"FlashMaster made studying fun! The gamification kept me hooked."</p>
                    <div class="flex items-center">
                        <div class="avatar">S</div>
                        <div class="ml-3">
                            <p class="font-semibold dark:text-white text-sm">Sarah M.</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">College Student</p>
                        </div>
                    </div>
                </div>
                <div class="bg-white dark:bg-gray-700 p-6 rounded-xl shadow-md" data-aos="fade-up" data-aos-delay="200">
                    <p class="text-gray-600 dark:text-gray-300 text-sm mb-4">"The progress tracking helped me ace my exams."</p>
                    <div class="flex items-center">
                        <div class="avatar">J</div>
                        <div class="ml-3">
                            <p class="font-semibold dark:text-white text-sm">James L.</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">High School Senior</p>
                        </div>
                    </div>
                </div>
                <div class="bg-white dark:bg-gray-700 p-6 rounded-xl shadow-md" data-aos="fade-up" data-aos-delay="300">
                    <p class="text-gray-600 dark:text-gray-300 text-sm mb-4">"Custom sessions fit my schedule perfectly."</p>
                    <div class="flex items-center">
                        <div class="avatar">E</div>
                        <div class="ml-3">
                            <p class="font-semibold dark:text-white text-sm">Emily R.</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Medical Student</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="py-20 bg-gradient-to-r from-[var(--primary)] to-[var(--secondary)] text-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center" data-aos="fade-up">
            <h2 class="text-3xl md:text-4xl font-bold font-inter mb-6 cta-heading text-black">Master Your Studies Today</h2>
            <p class="text-base mb-8 max-w-md mx-auto opacity-90">
                Start your journey to smarter learning now.
            </p>
            <a href="register.php" class="btn-primary px-4 py-2 rounded-full bg-white text-[var(--primary)] text-sm hover:bg-gray-100 relative overflow-hidden">
                <span class="relative z-10">Get Started Free</span>
                <span class="absolute inset-0 bg-[var(--secondary)] opacity-0 hover:opacity-20 transition-opacity"></span>
            </a>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-gray-800 dark:bg-gray-950 text-white py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col md:flex-row justify-between items-center">
                <div class="mb-6 md:mb-0">
                    <span class="text-xl font-bold font-inter bg-gradient-to-r from-[var(--primary)] to-[var(--secondary)] bg-clip-text text-transparent">
                        FlashMaster
                    </span>
                    <p class="text-gray-400 text-sm mt-2">Learn smarter, achieve more.</p>
                </div>
                <div class="flex space-x-6">
                    <a href="#features" class="text-gray-400 hover:text-white text-sm transition-colors">Features</a>
                    <a href="#testimonials" class="text-gray-400 hover:text-white text-sm transition-colors">Testimonials</a>
                    <a href="privacy.php" class="text-gray-400 hover:text-white text-sm transition-colors">Privacy Policy</a>
                </div>
            </div>
            <div class="text-center mt-8">
                <p class="text-gray-400 text-sm">© 2025 FlashMaster. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <!-- Back to Top -->
    <a href="#" class="back-to-top" aria-label="Back to top">
        <i class="fas fa-chevron-up"></i>
    </a>

    <!-- Scripts -->
    <script src="https://unpkg.com/aos@next/dist/aos.js"></script>
    <script>
        try {
            AOS.init({ duration: 800, once: true });
        } catch (e) {
            console.warn('AOS failed to initialize:', e);
        }
        const toggle = document.querySelector('.theme-toggle');
        const html = document.documentElement;
        toggle.addEventListener('click', () => {
            html.classList.toggle('dark');
            const icon = toggle.querySelector('i');
            icon.classList.toggle('fa-moon');
            icon.classList.toggle('fa-sun');
        });
        const menuToggle = document.querySelector('.mobile-menu-toggle');
        const mobileMenu = document.querySelector('.mobile-menu');
        menuToggle.addEventListener('click', () => {
            mobileMenu.classList.toggle('hidden');
            const icon = menuToggle.querySelector('i');
            icon.classList.toggle('fa-bars');
            icon.classList.toggle('fa-times');
        });
        const backToTop = document.querySelector('.back-to-top');
        window.addEventListener('scroll', () => {
            backToTop.classList.toggle('show', window.scrollY > 300);
        });
    </script>
</body>
</html>