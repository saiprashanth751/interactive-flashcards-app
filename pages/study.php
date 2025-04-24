<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

include '../includes/db.php';

// We get user id from session and deck id from url/route (like react param)...
// I am fetching user id to check mastery status...
$user_id = $_SESSION['user_id'];
$deck_id = $_GET['deck_id'] ?? null;

if (!$deck_id) {
    header("Location: index.php");
    exit();
}

// Fetch the deck with category_id included with mastery status..
$stmt = $pdo->prepare("SELECT d.id, d.title, d.category_id, COALESCE(udm.is_mastered, 0) AS is_mastered 
                       FROM decks d 
                       LEFT JOIN user_deck_mastery udm ON d.id = udm.deck_id AND udm.user_id = ? 
                       WHERE d.id = ?");
$stmt->execute([$user_id, $deck_id]);
$deck = $stmt->fetch();

if (!$deck) {
    header("Location: index.php");
    exit();
}

$deck['is_mastered'] = (bool) $deck['is_mastered'];
$category_id = $deck['category_id'];

// Fetch flashcards of that particular deck using simply query...
$stmt = $pdo->prepare("SELECT id, question, answer FROM flashcards WHERE deck_id = ?");
$stmt->execute([$deck_id]);
$flashcards = $stmt->fetchAll();

if (empty($flashcards)) {
    header("Location: index.php");
    exit();
}

// Fetch suggested decks in the same category (including shared decks)
// ignore the shared deck feature...need to work...
try {
    $stmt = $pdo->prepare("
        SELECT d.id, d.title, d.description 
        FROM decks d
        LEFT JOIN shared_decks sd ON d.id = sd.deck_id
        WHERE d.category_id = ? 
        AND d.id != ? 
        AND (d.user_id = ? OR sd.shared_with = ?)
        GROUP BY d.id
        ORDER BY d.created_at DESC 
        LIMIT 10
    ");
    $stmt->execute([$category_id, $deck_id, $user_id, $user_id]);
    $suggested_decks = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching suggested decks: " . $e->getMessage());
    $suggested_decks = [];
}

// Function to check achievements...ai code
function checkAchievements($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_deck_mastery WHERE user_id = ? AND is_mastered = 1");
    $stmt->execute([$user_id]);
    $mastered_decks = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM study_sessions WHERE user_id = ? AND status IN ('reviewed', 'mastered')");
    $stmt->execute([$user_id]);
    $studied_flashcards = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT id, name FROM achievements WHERE id NOT IN (SELECT achievement_id FROM user_achievements WHERE user_id = ?)");
    $stmt->execute([$user_id]);
    $available_achievements = $stmt->fetchAll();

    foreach ($available_achievements as $achievement) {
        if ($achievement['name'] === 'Deck Master' && $mastered_decks >= 5) {
            $stmt = $pdo->prepare("INSERT INTO user_achievements (user_id, achievement_id, unlocked_at) VALUES (?, ?, NOW())");
            $stmt->execute([$user_id, $achievement['id']]);
        } elseif ($achievement['name'] === 'Study Enthusiast' && $studied_flashcards >= 100) {
            $stmt = $pdo->prepare("INSERT INTO user_achievements (user_id, achievement_id, unlocked_at) VALUES (?, ?, NOW())");
            $stmt->execute([$user_id, $achievement['id']]);
        }
    }
}

// Handle mastery toggle
$debug_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_mastery'])) {
    $new_status = $deck['is_mastered'] ? 0 : 1;

    if ($new_status) {
        $stmt = $pdo->prepare("INSERT INTO user_deck_mastery (user_id, deck_id, is_mastered, mastered_at) 
                               VALUES (?, ?, ?, NOW()) 
                               ON DUPLICATE KEY UPDATE is_mastered = ?, mastered_at = NOW()");
        $stmt->execute([$user_id, $deck_id, 1, 1]);
        
        $stmt = $pdo->prepare("
            INSERT INTO study_sessions (user_id, deck_id, card_id, status, studied_at, next_review, interval_days)
            SELECT ?, ?, f.id, 'mastered', NOW(), DATE_ADD(NOW(), INTERVAL 1 DAY), 1
            FROM flashcards f WHERE f.deck_id = ?
            ON DUPLICATE KEY UPDATE status = 'mastered', studied_at = NOW(), next_review = DATE_ADD(NOW(), INTERVAL 1 DAY)
        ");
        $stmt->execute([$user_id, $deck_id, $deck_id]);

        $stmt = $pdo->prepare("INSERT INTO user_points (user_id, points) VALUES (?, 50)");
        $stmt->execute([$user_id]);

        checkAchievements($pdo, $user_id);
    } else {
        $stmt = $pdo->prepare("UPDATE user_deck_mastery SET is_mastered = 0, mastered_at = NULL WHERE user_id = ? AND deck_id = ?");
        $stmt->execute([$user_id, $deck_id]);
    }

    header("Location: study.php?deck_id=$deck_id");
    exit();
}

// Update last_studied_at
$stmt = $pdo->prepare("UPDATE decks SET last_studied_at = NOW() WHERE id = ?");
$stmt->execute([$deck_id]);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Study - <?php echo htmlspecialchars($deck['title']); ?></title>
    <link href="../src/output.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.11.5/gsap.min.js" defer></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.11.5/ScrollTrigger.min.js" defer></script>
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f3f4f6;
            color: #374151;
            min-height: 100vh;
        }

        .header-bg {
            background: linear-gradient(120deg, #3b82f6, #8b5cf6);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .btn-primary {
            background: #8b5cf6;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            transition: background 0.3s ease, transform 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary:hover {
            background: #7c3aed;
            transform: translateY(-2px);
        }

        .btn-primary:disabled {
            background: #d1d5db;
            cursor: not-allowed;
            transform: none;
        }

        .btn-secondary {
            color: white;
            transition: color 0.3s ease;
        }

        .btn-secondary:hover {
            color: #1e40af;
        }

        .btn-mastery {
            background: linear-gradient(120deg, #10b981, #059669);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            transition: background 0.3s ease, transform 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            width: 100%;
        }

        .btn-mastery:hover {
            background: linear-gradient(120deg, #059669, #047857);
            transform: translateY(-2px);
        }

        .btn-unmaster {
            background: linear-gradient(120deg, #ef4444, #dc2626);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            transition: background 0.3s ease, transform 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            width: 100%;
        }

        .btn-unmaster:hover {
            background: linear-gradient(120deg, #dc2626, #b91c1c);
            transform: translateY(-2px);
        }

        .flip-card {
            perspective: 1000px;
            background: rgba(255, 255, 255, 0.9);
            border-radius: 12px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            cursor: pointer;
        }

        .flip-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.15);
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
            border-radius: 12px;
            padding: 2rem;
            background: linear-gradient(135deg, #f3f4f6, #e5e7eb);
        }

        .flip-card-back {
            transform: rotateY(180deg);
        }

        .flipped .flip-card-inner {
            transform: rotateY(180deg);
        }

        .success-message {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid #10b981;
            border-radius: 8px;
            padding: 0.75rem;
            color: #10b981;
            font-size: 0.875rem;
        }

        .suggested-deck-card {
            background: rgba(255, 255, 255, 0.9);
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .suggested-deck-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.15);
        }

        .btn-study {
            background: #8b5cf6;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            transition: background 0.3s ease, transform 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-study:hover {
            background: #7c3aed;
            transform: translateY(-2px);
        }
    </style>
</head>

<body>
    <!-- Header -->
    <header class="header-bg text-white p-6 sticky top-0 z-20">
        <div class="max-w-6xl mx-auto flex justify-between items-center">
            <h1 class="text-3xl font-bold tracking-wide">Study - <?php echo htmlspecialchars($deck['title']); ?></h1>
            <div class="flex space-x-6">
                <a href="view_decks.php" class="btn-secondary flex items-center"><i class="fas fa-arrow-left mr-2"></i> Back to View Decks</a>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="p-6">
        <div class="max-w-lg mx-auto animated-section">
            <?php if ($debug_message): ?>
                <p class="success-message mb-4"><?php echo htmlspecialchars($debug_message); ?></p>
            <?php endif; ?>
            <form method="POST" action="study.php?deck_id=<?php echo $deck_id; ?>" id="masteryForm">
                <div class="flip-card w-full h-80 rounded-lg" id="flipCard">
                    <div class="flip-card-inner">
                        <div id="front" class="flip-card-front">
                            <p class="text-2xl font-semibold text-center"><?php echo htmlspecialchars($flashcards[0]['question']); ?></p>
                        </div>
                        <div id="back" class="flip-card-back">
                            <p class="text-2xl font-semibold text-center"><?php echo htmlspecialchars($flashcards[0]['answer']); ?></p>
                        </div>
                    </div>
                </div>
                <div id="masteryButtonContainer" class="mt-4"></div>
            </form>
            <div class="flex justify-between mt-6">
                <button class="btn-primary" id="prevBtn"><i class="fas fa-chevron-left"></i> Previous</button>
                <button class="btn-primary" id="nextBtn">Next <i class="fas fa-chevron-right"></i></button>
            </div>
            <input type="hidden" id="viewedCards" value="[]">
            <p id="viewProgress" class="text-sm text-gray-500 mt-2 text-center">Viewed: 0/<?php echo count($flashcards); ?> cards</p>
        </div>

        <!-- Suggested Decks Section -->
        <div class="max-w-6xl mx-auto mt-8 animated-section">
            <h2 class="text-2xl font-semibold text-gray-800 mb-6">Suggested Decks in This Category</h2>
            <?php if (empty($suggested_decks)): ?>
                <div class="bg-blue-50 p-4 rounded-lg">
                    <p class="text-blue-800">No other decks found in this category.
                        <a href="create_deck.php?category=<?php echo $category_id; ?>" class="text-blue-600 underline">
                            Create a new deck in this category
                        </a>
                    </p>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php foreach ($suggested_decks as $suggested_deck): ?>
                        <div class="suggested-deck-card">
                            <h3 class="text-lg font-semibold text-gray-800 mb-2"><?php echo htmlspecialchars($suggested_deck['title']); ?></h3>
                            <p class="text-gray-600 text-sm mb-4"><?php echo htmlspecialchars($suggested_deck['description'] ?: 'No description'); ?></p>
                            <a href="study.php?deck_id=<?php echo $suggested_deck['id']; ?>" class="btn-study">
                                <i class="fas fa-book-open mr-2"></i> Study Now
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- JavaScript -->
    <script>
        window.addEventListener('load', function() {
            // GSAP Animations
            gsap.registerPlugin(ScrollTrigger);
            gsap.utils.toArray('.animated-section').forEach(section => {
                gsap.from(section, {
                    scrollTrigger: {
                        trigger: section,
                        start: 'top 85%',
                        toggleActions: 'play none none none',
                        once: true
                    },
                    y: 30,
                    opacity: 0,
                    duration: 0.6,
                    ease: 'power2.out'
                });
            });

            // Variables and DOM elements
            let currentCardIndex = 0;
            const flashcards = <?php echo json_encode($flashcards); ?>;
            const totalCards = flashcards.length;
            const prevBtn = document.getElementById('prevBtn');
            const nextBtn = document.getElementById('nextBtn');
            const flipCardElement = document.getElementById('flipCard');
            const isMastered = <?php echo $deck['is_mastered'] ? 'true' : 'false'; ?>;
            const deckId = <?php echo $deck_id; ?>;
            const userId = <?php echo $user_id; ?>;

            function flipCard() {
                flipCardElement.classList.toggle('flipped');
            }

            function updateStudySession(cardId) {
                fetch('update_study_session.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: `user_id=${userId}&deck_id=${deckId}&card_id=${cardId}`
                }).catch(error => console.error('Error updating study session:', error));
            }

            function showCard(index) {
                if (index < 0 || index >= totalCards) return;

                const front = document.getElementById('front');
                const back = document.getElementById('back');
                const masteryButtonContainer = document.getElementById('masteryButtonContainer');
                front.innerHTML = `<p class="text-2xl font-semibold text-center">${flashcards[index].question}</p>`;
                back.innerHTML = `<p class="text-2xl font-semibold text-center">${flashcards[index].answer}</p>`;
                flipCardElement.classList.remove('flipped');

                const viewedCards = JSON.parse(document.getElementById('viewedCards').value);
                if (!viewedCards.includes(index)) {
                    viewedCards.push(index);
                    document.getElementById('viewedCards').value = JSON.stringify(viewedCards);
                    updateStudySession(flashcards[index].id);
                }
                updateProgress();

                if (index === totalCards - 1) {
                    const buttonDisabled = viewedCards.length < totalCards ? 'disabled' : '';
                    masteryButtonContainer.innerHTML = `
                        <button type="submit" name="toggle_mastery" id="masteryBtn"
                            class="w-full py-2 px-4 rounded-md text-white ${isMastered ? 'btn-unmaster' : 'btn-mastery'}"
                            ${buttonDisabled}>
                            <i class="fas ${isMastered ? 'fa-times' : 'fa-check'} mr-2"></i>
                            ${isMastered ? 'Mark as Not Mastered' : 'Mark Deck as Mastered'}
                        </button>
                    `;
                } else {
                    masteryButtonContainer.innerHTML = '';
                }

                prevBtn.disabled = (index === 0);
                nextBtn.disabled = (index === totalCards - 1);
            }

            function updateProgress() {
                const viewedCards = JSON.parse(document.getElementById('viewedCards').value);
                document.getElementById('viewProgress').textContent = `Viewed: ${viewedCards.length}/${totalCards} cards`;
                const masteryBtn = document.getElementById('masteryBtn');
                if (masteryBtn && viewedCards.length === totalCards) {
                    masteryBtn.disabled = false;
                }
            }

            function nextCard() {
                if (currentCardIndex < totalCards - 1) {
                    currentCardIndex++;
                    showCard(currentCardIndex);
                }
            }

            function prevCard() {
                if (currentCardIndex > 0) {
                    currentCardIndex--;
                    showCard(currentCardIndex);
                }
            }

            // Initialize and bind events
            if (totalCards > 0) {
                showCard(0);
                prevBtn.addEventListener('click', prevCard);
                nextBtn.addEventListener('click', nextCard);
                flipCardElement.addEventListener('click', flipCard);
            } else {
                prevBtn.disabled = true;
                nextBtn.disabled = true;
            }
        });
    </script>
</body>
</html>