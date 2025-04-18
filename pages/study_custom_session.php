<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

include '../includes/db.php';
$user_id = $_SESSION['user_id'];
$session_id = $_GET['session_id'] ?? 0;
$deck_id = $_GET['deck_id'] ?? 0;

if ($session_id <= 0) {
    header("Location: index.php");
    exit();
}

$stmt = $pdo->prepare("SELECT name, time_limit, status FROM custom_study_sessions WHERE id = ? AND user_id = ?");
$stmt->execute([$session_id, $user_id]);
$session = $stmt->fetch();
if (!$session) {
    header("Location: index.php");
    exit();
}

// Fetch selected decks and their statuses
$stmt = $pdo->prepare("
    SELECT csd.deck_id, csd.status, d.title
    FROM custom_study_session_decks csd
    JOIN decks d ON csd.deck_id = d.id
    WHERE csd.session_id = ?
");
$stmt->execute([$session_id]);
$decks = $stmt->fetchAll();

// Calculate remaining time
$timer_data = $_SESSION['timer'][$session_id] ?? null;
if ($timer_data) {
    $time_limit = $timer_data['time_limit'];
    $start_time = $timer_data['start_time'];
    $elapsed_time = time() - $start_time;
    $time_left = max(0, $time_limit - $elapsed_time);
} else {
    $time_left = $session['time_limit'] * 60; // Fallback if timer data is not set
}

// If a deck is selected, fetch its flashcards
$flashcards = [];
$current_flashcard = null;
$current_index = isset($_SESSION['current_index'][$session_id][$deck_id]) ? $_SESSION['current_index'][$session_id][$deck_id] : 0;

if ($deck_id > 0) {
    $stmt = $pdo->prepare("
        SELECT f.id, f.question, f.answer
        FROM custom_study_session_flashcards csf
        JOIN flashcards f ON csf.flashcard_id = f.id
        WHERE csf.session_id = ? AND f.deck_id = ?
    ");
    $stmt->execute([$session_id, $deck_id]);
    $flashcards = $stmt->fetchAll();

    if (!empty($flashcards)) {
        if ($current_index >= 0 && $current_index < count($flashcards)) {
            $current_flashcard = $flashcards[$current_index];
        } else {
            $current_index = 0;
            $current_flashcard = $flashcards[$current_index];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Study Custom Session</title>
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
        .btn-disabled {
            @apply opacity-50 cursor-not-allowed;
        }
        .flip-card {
            perspective: 1000px;
            height: 300px;
        }
        .flip-card-inner {
            position: relative;
            width: 100%;
            height: 100%;
            transition: transform 0.6s;
            transform-style: preserve-3d;
        }
        .flip-card-front,
        .flip-card-back {
            position: absolute;
            width: 100%;
            height: 100%;
            backface-visibility: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 0.5rem;
            padding: 2rem;
            background-color: white;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .flip-card-back {
            transform: rotateY(180deg);
            background-color: #f9fafb;
        }
        .flipped .flip-card-inner {
            transform: rotateY(180deg);
        }
        .deck-card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .deck-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px rgba(0, 0, 0, 0.1);
        }
        .button-container {
            opacity: 1 !important;
            transform: none !important;
        }
    </style>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.11.5/gsap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.11.5/ScrollTrigger.min.js"></script>
    <script>
        let timeLeft = <?php echo $time_left; ?>;
        const totalCards = <?php echo count($flashcards); ?>;
        let currentIndex = <?php echo $current_index; ?>;
        const flashcards = <?php echo json_encode($flashcards); ?>;

        function startTimer() {
            let timer = setInterval(() => {
                if (timeLeft <= 0) {
                    clearInterval(timer);
                    alert("Time's up!");
                    fetch('update_custom_study.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `session_id=<?php echo $session_id; ?>&action=end_session`
                    }).then(() => {
                        window.location.href = "index.php";
                    });
                } else {
                    timeLeft--;
                    let minutes = Math.floor(timeLeft / 60);
                    let seconds = timeLeft % 60;
                    document.getElementById("timer").innerText = `${minutes}:${seconds < 10 ? '0' : ''}${seconds}`;
                }
            }, 1000);
        }

        function flipCard() {
            const flipCard = document.getElementById('flipCard');
            if (flipCard) flipCard.classList.toggle('flipped');
        }

        function showCard(index) {
            const front = document.getElementById('front');
            const back = document.getElementById('back');
            const progress = document.getElementById('progress');
            const prevBtn = document.getElementById('prevBtn');
            const nextBtn = document.getElementById('nextBtn');
            const finishBtnContainer = document.getElementById('finishBtnContainer');

            if (front && back && progress && prevBtn && nextBtn) {
                front.innerHTML = `<p class="text-xl font-semibold text-center">${flashcards[index].question}</p>`;
                back.innerHTML = `<p class="text-xl font-semibold text-center">${flashcards[index].answer}</p>`;
                progress.textContent = `Card ${index + 1} of ${totalCards}`;

                const flipCardElement = document.getElementById('flipCard');
                if (flipCardElement) flipCardElement.classList.remove('flipped');

                prevBtn.classList.toggle('btn-disabled', index === 0);
                nextBtn.classList.toggle('btn-disabled', index === totalCards - 1);

                // Show/hide Finish Deck button
                if (finishBtnContainer) {
                    finishBtnContainer.style.display = (index === totalCards - 1) ? 'flex' : 'none';
                }
            }
        }

        function nextCard() {
            if (currentIndex < totalCards - 1) {
                currentIndex++;
                showCard(currentIndex);
                fetch('update_index.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `session_id=<?php echo $session_id; ?>&deck_id=<?php echo $deck_id; ?>&index=${currentIndex}`
                }).catch(error => console.error('Error updating index:', error));
            }
        }

        function prevCard() {
            if (currentIndex > 0) {
                currentIndex--;
                showCard(currentIndex);
                fetch('update_index.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `session_id=<?php echo $session_id; ?>&deck_id=<?php echo $deck_id; ?>&index=${currentIndex}`
                }).catch(error => console.error('Error updating index:', error));
            }
        }

        window.onload = () => {
            startTimer();
            if (totalCards > 0) {
                showCard(currentIndex);
                gsap.set('.button-container', { opacity: 1, y: 0 });
            }
        };
    </script>
</head>
<body class="bg-gray-50 min-h-screen">
    <!-- Header -->
    <header class="bg-gradient-to-r from-purple-700 to-indigo-700 text-white p-6 shadow-lg sticky top-0 z-20">
        <div class="max-w-4xl mx-auto flex justify-between items-center">
            <h1 class="text-3xl font-bold tracking-wide"><?php echo htmlspecialchars($session['name']); ?></h1>
            <div class="flex items-center space-x-6">
                <form action="update_custom_study.php" method="POST" class="inline">
                    <input type="hidden" name="session_id" value="<?php echo $session_id; ?>">
                    <input type="hidden" name="action" value="end_session">
                    <button type="submit" class="text-sm hover:underline transition duration-300 flex items-center">
                        <i class="fas fa-stop mr-2"></i> End Session
                    </button>
                </form>
                <a href="index.php" class="text-sm hover:underline transition duration-300 flex items-center">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Dashboard
                </a>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="p-6">
        <div class="max-w-4xl mx-auto">
            <div class="flex justify-between items-center mb-6 animated-section">
                <p class="text-gray-600">Time Remaining: <span id="timer" class="font-semibold text-purple-600"><?php echo floor($time_left / 60); ?>:<?php echo str_pad($time_left % 60, 2, '0', STR_PAD_LEFT); ?></span></p>
                <?php if ($deck_id > 0): ?>
                    <a href="study_custom_session.php?session_id=<?php echo $session_id; ?>" class="text-purple-600 hover:underline flex items-center">
                        <i class="fas fa-arrow-left mr-2"></i> Back to Decks
                    </a>
                <?php endif; ?>
            </div>

            <?php if ($deck_id > 0): ?>
                <!-- Studying a Deck -->
                <?php if (!empty($flashcards)): ?>
                    <div class="flip-card w-full bg-white rounded-lg shadow-md cursor-pointer animated-section text-black" onclick="flipCard()" id="flipCard">
                        <div class="flip-card-inner">
                            <div class="flip-card-front" id="front">
                                <p class="text-xl font-semibold text-center"><?php echo htmlspecialchars($current_flashcard['question'] ?? 'Loading...'); ?></p>
                            </div>
                            <div class="flip-card-back" id="back">
                                <p class="text-xl font-semibold text-center"><?php echo htmlspecialchars($current_flashcard['answer'] ?? 'Loading...'); ?></p>
                            </div>
                        </div>
                    </div>
                    <p id="progress" class="text-sm text-gray-600 mt-4 text-center animated-section">Card <?php echo $current_index + 1; ?> of <?php echo count($flashcards); ?></p>
                    <div class="flex justify-between mt-6 button-container">
                        <button onclick="prevCard()" class="btn-primary flex items-center <?php echo $current_index === 0 ? 'btn-disabled' : ''; ?>" id="prevBtn">
                            <i class="fas fa-arrow-left mr-2"></i> Previous
                        </button>
                        <button onclick="nextCard()" class="btn-primary flex items-center <?php echo $current_index === count($flashcards) - 1 ? 'btn-disabled' : ''; ?>" id="nextBtn">
                            Next <i class="fas fa-arrow-right ml-2"></i>
                        </button>
                    </div>
                    <div class="mt-6 flex justify-center space-x-4 button-container" id="finishBtnContainer" style="display: <?php echo $current_index === count($flashcards) - 1 ? 'flex' : 'none'; ?>;">
                        <form action="update_custom_study.php" method="POST" class="inline">
                            <input type="hidden" name="session_id" value="<?php echo $session_id; ?>">
                            <input type="hidden" name="deck_id" value="<?php echo $deck_id; ?>">
                            <input type="hidden" name="action" value="complete">
                            <button type="submit" class="btn-primary flex items-center">
                                <i class="fas fa-check mr-2"></i> Finish Deck
                            </button>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="bg-white p-8 rounded-lg shadow-md text-center animated-section">
                        <i class="fas fa-exclamation-triangle text-4xl text-yellow-500 mb-4"></i>
                        <h3 class="text-lg font-semibold text-gray-800 mb-2">No Flashcards Found</h3>
                        <p class="text-gray-600 mb-6">This deck has no flashcards available for study.</p>
                        <a href="study_custom_session.php?session_id=<?php echo $session_id; ?>" class="btn-primary inline-flex items-center">
                            <i class="fas fa-arrow-left mr-2"></i> Back to Decks
                        </a>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <!-- Deck Selection Screen -->
                <div class="bg-white p-8 rounded-lg shadow-md animated-section">
                    <h2 class="text-2xl font-semibold text-gray-800 mb-6 flex items-center">
                        <i class="fas fa-book-open mr-2 text-purple-600"></i> Select a Deck to Study
                    </h2>
                    <?php if (empty($decks)): ?>
                        <p class="text-gray-600 text-center">No decks available for this session.</p>
                    <?php else: ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <?php foreach ($decks as $deck): ?>
                                <div class="deck-card bg-white p-6 rounded-lg shadow-md border <?php echo $deck['status'] === 'completed' ? 'border-green-500' : 'border-gray-200'; ?> animated-card">
                                    <h3 class="text-lg font-semibold text-gray-800 mb-2"><?php echo htmlspecialchars($deck['title']); ?></h3>
                                    <p class="text-sm text-gray-500 mb-4">Status: 
                                        <span class="<?php echo $deck['status'] === 'completed' ? 'text-green-500' : 'text-yellow-500'; ?>">
                                            <?php echo $deck['status'] === 'completed' ? 'Completed' : 'Pending'; ?>
                                        </span>
                                    </p>
                                    <?php if ($deck['status'] !== 'completed'): ?>
                                        <a href="study_custom_session.php?session_id=<?php echo $session_id; ?>&deck_id=<?php echo $deck['deck_id']; ?>" class="btn-primary inline-flex items-center">
                                            <i class="fas fa-play mr-2"></i> Study Deck
                                        </a>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php
                        $all_completed = true;
                        foreach ($decks as $deck) {
                            if ($deck['status'] !== 'completed') {
                                $all_completed = false;
                                break;
                            }
                        }
                        if ($all_completed) {
                            $stmt = $pdo->prepare("UPDATE custom_study_sessions SET status = 'completed' WHERE id = ?");
                            $stmt->execute([$session_id]);
                        }
                        ?>
                        <?php if ($all_completed): ?>
                            <div class="mt-8 text-center animated-section">
                                <p class="text-green-500 font-semibold mb-4 flex items-center justify-center">
                                    <i class="fas fa-check-circle mr-2"></i> All decks in this session have been completed!
                                </p>
                                <a href="index.php" class="text-purple-600 hover:underline inline-flex items-center">
                                    <i class="fas fa-arrow-left mr-2"></i> Back to Dashboard
                                </a>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- GSAP Animations -->
    <script>
        gsap.registerPlugin(ScrollTrigger);

        gsap.utils.toArray('.animated-section:not(.button-container)').forEach(section => {
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
                ease: "power2.out"
            });
        });

        gsap.utils.toArray('.animated-card').forEach(card => {
            gsap.from(card, {
                scrollTrigger: {
                    trigger: card,
                    start: "top 85%",
                    toggleActions: "play none none none",
                    once: true
                },
                duration: 0.8,
                x: -50,
                opacity: 0,
                ease: "power2.out"
            });
        });
    </script>
</body>
</html>