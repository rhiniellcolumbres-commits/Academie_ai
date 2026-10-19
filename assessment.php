<?php

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$user = getUserById($pdo, $_SESSION['user_id']);

if (!$user) {
    session_destroy();
    header('Location: ./');
    exit;
}

$assessmentStmt = $pdo->prepare(
    'SELECT * FROM assessments WHERE user_id = ? LIMIT 1'
);
$assessmentStmt->execute([(int) $_SESSION['user_id']]);
$savedAssessment = $assessmentStmt->fetch(PDO::FETCH_ASSOC) ?: [];
$savedAnswerData = json_decode((string) ($savedAssessment['assessment_answers'] ?? ''), true);
$savedQuestionAnswers = is_array($savedAnswerData['answers'] ?? null)
    ? $savedAnswerData['answers']
    : [];

$roadmapStmt = $pdo->prepare(
    'SELECT id FROM study_paths WHERE user_id = ? LIMIT 1'
);
$roadmapStmt->execute([(int) $_SESSION['user_id']]);
$hasExistingRoadmap = (bool) $roadmapStmt->fetchColumn();
$isRetake = isset($_GET['retake']) && $_GET['retake'] === '1';

// Students with an existing roadmap must intentionally choose to retake the assessment.
if ($hasExistingRoadmap && !$isRetake) {
    redirect('dashboard?notice=assessment_exists');
}

// Do not show the retake warning if the student has no roadmap to replace.
if (!$hasExistingRoadmap) {
    $isRetake = false;
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Assessment | Academie</title>

    <link
        rel="stylesheet"
        href="css/main.css"
    >

</head>

<body class="assessment-page">

    <!-- Shared student navbar -->
    <?php require __DIR__ . '/includes/navbar.php'; ?>


    <!-- =========================
         ASSESSMENT
    ========================== -->

    <main class="assessment-wrapper">

        <div class="assessment-intro">

            <span class="eyebrow">
                <span class="brand-name"><span class="brand-acad">Acad</span><span class="brand-amie">emie</span></span>
            </span>

            <h1>
                Let's build your learning path
            </h1>

            <p>
                Discover a technology path that fits your interests, then follow an AI-generated roadmap with clear milestones from your current level toward professional readiness.
            </p>

        </div>


        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-warning text-center mb-4">
                Please complete all assessment questions.
            </div>
        <?php endif; ?>

        <?php if ($isRetake): ?>
            <div class="alert alert-warning mb-4" role="alert">
                <strong>You're retaking your assessment.</strong>
                Your current roadmap and its stage/activity progress will be replaced when you generate the new roadmap.
                Your previous answers are prefilled so you can update only what has changed.
            </div>
        <?php endif; ?>

        <form
            id="assessmentForm"
            action="actions/save-assessment.php"
            method="POST"
        >
            <input type="hidden" name="retake_assessment" value="<?= $isRetake ? '1' : '0' ?>">
            <!-- =========================
                 CURRENT LEVEL
            ========================== -->

            <section class="assessment-section">

                <div class="assessment-section-header">

                    <span class="question-number">
                        01
                    </span>

                    <div>

                        <h2>
                            What's your current level?
                        </h2>

                        <p>
                            Choose your current skill level. The AI will use this to decide where your roadmap should begin.
                        </p>

                    </div>

                </div>


                <div class="assessment-options">


                    <label class="assessment-option">

                        <input
                            type="radio"
                            name="current_level"
                            value="Complete Beginner"
                            required <?= (($user['current_level'] ?? '') === 'Complete Beginner') ? 'checked' : '' ?>
                        >

                        <span class="option-content">

                            <strong>
                                Complete Beginner
                            </strong>

                            <small>
                                I'm new to technology and don't know
                                where to start.
                            </small>

                        </span>

                    </label>


                    <label class="assessment-option">

                        <input
                            type="radio"
                            name="current_level"
                            value="Beginner" <?= (($user['current_level'] ?? '') === 'Beginner') ? 'checked' : '' ?>
                        >

                        <span class="option-content">

                            <strong>
                                Beginner
                            </strong>

                            <small>
                                I know some basics but still need
                                guidance.
                            </small>

                        </span>

                    </label>


                    <label class="assessment-option">

                        <input
                            type="radio"
                            name="current_level"
                            value="Intermediate" <?= (($user['current_level'] ?? '') === 'Intermediate') ? 'checked' : '' ?>
                        >

                        <span class="option-content">

                            <strong>
                                Intermediate
                            </strong>

                            <small>
                                I understand the fundamentals and
                                have some practical experience.
                            </small>

                        </span>

                    </label>


                    <label class="assessment-option">

                        <input
                            type="radio"
                            name="current_level"
                            value="Advanced" <?= (($user['current_level'] ?? '') === 'Advanced') ? 'checked' : '' ?>
                        >

                        <span class="option-content">

                            <strong>
                                Advanced
                            </strong>

                            <small>
                                I already have strong knowledge and
                                want to improve further.
                            </small>

                        </span>

                    </label>


                </div>

            </section>


            <!-- =========================
                 IMPROVEMENT GOAL
            ========================== -->

            <!-- =========================
     IMPROVEMENT GOAL
========================== -->

<section class="assessment-section">

    <div class="assessment-section-header">

        <span class="question-number">
            02
        </span>

        <div>

            <h2>
                What do you want to improve or achieve?
            </h2>

            <p>
                Choose the main reason you want to learn.
            </p>

        </div>

    </div>


    <div class="assessment-options">


        <label class="assessment-option">

            <input
                type="radio"
                name="improvement_goal"
                value="Learn the Fundamentals"
                required <?= (($user['improvement_goal'] ?? '') === 'Learn the Fundamentals') ? 'checked' : '' ?>
            >

            <span class="option-content">

                <strong>
                    Learn the Fundamentals
                </strong>

                <small>
                    Build a strong foundation before moving
                    to more advanced topics.
                </small>

            </span>

        </label>


        <label class="assessment-option">

            <input
                type="radio"
                name="improvement_goal"
                value="Improve My Skills" <?= (($user['improvement_goal'] ?? '') === 'Improve My Skills') ? 'checked' : '' ?>
            >

            <span class="option-content">

                <strong>
                    Improve My Skills
                </strong>

                <small>
                    Strengthen what I already know and develop
                    better skills and techniques.
                </small>

            </span>

        </label>


        <label class="assessment-option">

            <input
                type="radio"
                name="improvement_goal"
                value="Build Practical Experience" <?= (($user['improvement_goal'] ?? '') === 'Build Practical Experience') ? 'checked' : '' ?>
            >

            <span class="option-content">

                <strong>
                    Build Practical Experience
                </strong>

                <small>
                    Learn by applying knowledge to practical
                    tasks, activities, or projects.
                </small>

            </span>

        </label>


        <label class="assessment-option">

            <input
                type="radio"
                name="improvement_goal"
                value="Prepare for a Career" <?= (($user['improvement_goal'] ?? '') === 'Prepare for a Career') ? 'checked' : '' ?>
            >

            <span class="option-content">

                <strong>
                    Prepare for a Career
                </strong>

                <small>
                    Develop the knowledge and skills needed
                    for my future career.
                </small>

            </span>

        </label>


        <label class="assessment-option">

            <input
                type="radio"
                name="improvement_goal"
                value="Prepare for Further Studies" <?= (($user['improvement_goal'] ?? '') === 'Prepare for Further Studies') ? 'checked' : '' ?>
            >

            <span class="option-content">

                <strong>
                    Prepare for Further Studies
                </strong>

                <small>
                    Strengthen my knowledge for advanced
                    study or specialization.
                </small>

            </span>

        </label>


    </div>

</section>


            <!-- GENERAL CAREER MATCHING ASSESSMENT -->
            <section class="assessment-section">
                <div class="assessment-section-header">
                    <span class="question-number">03</span>
                    <div>
                        <h2>Discover your best-fit technology path</h2>
                        <p>Answer five quick questions to get a technology path recommendation. Choose one option for each; there are no wrong answers, and you don’t need to know your career goal yet.</p>
                    </div>
                </div>
                <?php
                $assessmentQuestions = [
                    'q_interest' => ['What would you most enjoy creating?', [
                        'FULLSTACK' => 'A complete website or application, from screen to database',
                        'FRONTEND' => 'An attractive, interactive website that people use',
                        'BACKEND' => 'The server, database, and logic behind an application',
                        'UIUX' => 'Easy-to-use layouts, prototypes, and user experiences',
                        'AIML' => 'An intelligent tool that learns from data or recognizes patterns',
                        'GAMEDEV' => 'A playable game with mechanics and interactive worlds',
                        'SE' => 'Reliable software that solves problems using clean code'
                    ]],
                    'q_activity' => ['Which activity sounds most satisfying?', [
                        'FULLSTACK' => 'Connecting a user interface, API, and database',
                        'FRONTEND' => 'Building page layouts and interactive features',
                        'BACKEND' => 'Designing APIs, data structures, and application logic',
                        'UIUX' => 'Sketching screens and improving how people use a product',
                        'AIML' => 'Experimenting with data, models, and predictions',
                        'GAMEDEV' => 'Programming game controls, rules, and levels',
                        'SE' => 'Breaking down a problem and designing a maintainable solution'
                    ]],
                    'q_strength' => ['Which kind of challenge would you prefer?', [
                        'FULLSTACK' => 'Making all parts of an application work together',
                        'FRONTEND' => 'Making a website responsive and visually clear',
                        'BACKEND' => 'Making data processing secure and efficient',
                        'UIUX' => 'Finding a simple, accessible solution for users',
                        'AIML' => 'Finding patterns and testing possible solutions',
                        'GAMEDEV' => 'Making gameplay fun, balanced, and smooth',
                        'SE' => 'Planning, testing, and improving software quality'
                    ]],
                    'q_interest_area' => ['Which topic are you most curious to learn?', [
                        'FULLSTACK' => 'Frontend, backend, and databases together',
                        'FRONTEND' => 'HTML, CSS, JavaScript, and web interfaces',
                        'BACKEND' => 'Programming logic, SQL, servers, and REST APIs',
                        'UIUX' => 'Design systems, wireframes, and usability',
                        'AIML' => 'Artificial intelligence, data, and machine learning',
                        'GAMEDEV' => 'Game engines, 2D/3D worlds, and game scripting',
                        'SE' => 'Algorithms, software design, testing, and code quality'
                    ]],
                    'q_goal' => ['What would you most like to build first?', [
                        'FULLSTACK' => 'A complete web app with login and saved data',
                        'FRONTEND' => 'A polished portfolio or interactive website',
                        'BACKEND' => 'A secure API connected to a database',
                        'UIUX' => 'A clickable prototype for an app or website',
                        'AIML' => 'A small AI or data-based prediction project',
                        'GAMEDEV' => 'A small playable 2D game',
                        'SE' => 'A well-tested program that solves a practical problem'
                    ]]
                ];
                $questionIndex = 0;
                foreach ($assessmentQuestions as $field => $question):
                    $questionIndex++;
                ?>
                <div class="path-question">
                    <div class="path-question-heading">
                        <span class="path-question-number"><?= sprintf('%02d', $questionIndex) ?></span>
                        <h3><?= e($question[0]) ?></h3>
                        <span class="path-single-choice">Choose one</span>
                    </div>
                    <div class="assessment-options path-choice-grid">
                        <?php foreach ($question[1] as $code => $label): ?>
                        <label class="assessment-option path-choice">
                            <input type="radio" name="<?= e($field) ?>" value="<?= e($code) ?>" required <?= (($savedQuestionAnswers[$field] ?? '') === $code) ? 'checked' : '' ?>>
                            <span class="option-content"><strong><?= e($label) ?></strong></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
                <input type="hidden" name="career_goal" value="">
            </section>

            <!-- =========================
                 PERSONAL DETAILS FOR AI
            ========================== -->

            <section class="assessment-section">

                <div class="assessment-section-header">

                    <span class="question-number">
                        04
                    </span>

                    <div>
                        <h2>Personalize your IT roadmap</h2>
                        <p>Share the IT topics you want to explore and the technical skills you already have. These optional details help tailor your learning steps.</p>
                    </div>

                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="interests">IT interests</label>
                        <textarea class="form-control" id="interests" name="interests" rows="4" placeholder="e.g. web development, cybersecurity, networking, data, or AI"><?= e($user['interests'] ?? '') ?></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="skills">IT skills you already have</label>
                        <textarea class="form-control" id="skills" name="skills" rows="4" placeholder="e.g. HTML/CSS, Python, SQL, troubleshooting, or computer hardware"><?= e($user['skills'] ?? '') ?></textarea>
                    </div>
                </div>

            </section>


            <!-- =========================
                 BUTTONS
            ========================== -->

            <div class="assessment-actions">

                <a
                    href="<?= $isRetake ? 'dashboard' : './' ?>"
                    class="assessment-back"
                >
                    ← Back
                </a>

                <button
                    type="submit"
                    class="assessment-start"
                >
                    <?= $isRetake ? 'Generate New Roadmap →' : 'Generate My Roadmap →' ?>
                </button>

            </div>


        </form>

    </main>


    <script>

    // Attach client-side validation after the assessment form is in the page.
    document.addEventListener('DOMContentLoaded', function () {

        const form = document.getElementById('assessmentForm');

        if (!form) {
            return;
        }

        // Prevent submission when any required answer is missing.
        form.addEventListener('submit', function (event) {

            const level = form.querySelector(
                'input[name="current_level"]:checked'
            );

            const goal = form.querySelector(
                'input[name="improvement_goal"]:checked'
            );

            const requiredQuestions = ['q_interest', 'q_activity', 'q_strength', 'q_interest_area', 'q_goal'];
            // Check each required question for a selected radio answer.
            const allAnswered = requiredQuestions.every(function (name) {
                return form.querySelector('input[name="' + name + '"]:checked');
            });

            if (!level || !goal || !allAnswered) {

                event.preventDefault();

                alert(
                    'Please complete all assessment questions before continuing.'
                );

            }

        });

    });

</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
