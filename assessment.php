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
            <div class="assessment-retake-notice" role="alert">
                <span class="assessment-retake-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none">
                        <path d="M12 3.5 2.9 19.2a1.5 1.5 0 0 0 1.3 2.3h15.6a1.5 1.5 0 0 0 1.3-2.3L12 3.5Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" />
                        <path d="M12 9v5m0 3h.01" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                    </svg>
                </span>
                <div class="assessment-retake-copy">
                    <h2>You're updating your assessment</h2>
                    <p>Generating a new roadmap will replace your current roadmap and reset its stage and activity progress.</p>
                    <span class="assessment-retake-hint">Your previous answers are filled in. Review them and change anything that has changed.</span>
                </div>
            </div>
        <?php endif; ?>

        <form
            id="assessmentForm"
            action="actions/save-assessment.php"
            method="POST"
        >
            <input type="hidden" name="retake_assessment" value="<?= $isRetake ? '1' : '0' ?>">
            <div class="assessment-wizard-progress" aria-live="polite" aria-label="Assessment progress">
                <div><span id="assessmentStepLabel">Step 1 of 4</span><span id="assessmentProgressPercent">25%</span></div>
                <div class="assessment-progress-track"><span id="assessmentProgressBar" style="width:25%"></span></div>
            </div>
            <p class="assessment-step-error" id="assessmentStepError" role="alert" hidden></p>
            <!-- =========================
                 CURRENT LEVEL
            ========================== -->

            <section class="assessment-section assessment-step" data-assessment-step>

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

<section class="assessment-section assessment-step" data-assessment-step>

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


            <!-- Each path question is its own step so learners see a focused screen. -->
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
                ?>
                <section class="assessment-section assessment-step path-question" data-assessment-step>
                    <div class="assessment-section-header">
                        <span class="question-number">03</span>
                        <div>
                            <h2>Discover your best-fit technology</h2>
                            <p>Answer these five questions based on what interests you. Your choices help us recommend a technology specialization and shape your roadmap.</p>
                        </div>
                    </div>

                    <?php
                    $questionIndex = 0;
                    foreach ($assessmentQuestions as $field => $question):
                        $questionIndex++;
                    ?>
                    <div class="path-question-item">
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
                </section>
                <input type="hidden" name="career_goal" value="">

            <!-- =========================
                 PERSONAL DETAILS FOR AI
            ========================== -->

            <section class="assessment-section assessment-step" data-assessment-step>

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

            <div class="assessment-actions assessment-wizard-actions">
                <button type="button" class="assessment-back" id="assessmentBackBtn" disabled>← Previous</button>
                <button type="button" class="assessment-start" id="assessmentNextBtn">Next →</button>
                <button type="submit" class="assessment-start" id="assessmentSubmitBtn" hidden>
                    <?= $isRetake ? 'Generate New Roadmap →' : 'Generate My Roadmap →' ?>
                </button>
            </div>


        </form>

    </main>


    <script>

    // Show one assessment step at a time and validate each required answer before advancing.
    document.addEventListener('DOMContentLoaded', function () {

        const form = document.getElementById('assessmentForm');

        if (!form) {
            return;
        }

        const steps = Array.from(form.querySelectorAll('[data-assessment-step]'));
        const backButton = document.getElementById('assessmentBackBtn');
        const nextButton = document.getElementById('assessmentNextBtn');
        const submitButton = document.getElementById('assessmentSubmitBtn');
        const stepLabel = document.getElementById('assessmentStepLabel');
        const percentLabel = document.getElementById('assessmentProgressPercent');
        const progressBar = document.getElementById('assessmentProgressBar');
        const errorMessage = document.getElementById('assessmentStepError');
        let activeStep = 0;

        function stepIsComplete(step) {
            const requiredRadios = Array.from(step.querySelectorAll('input[type="radio"][required]'));
            const groupNames = [...new Set(requiredRadios.map(function (input) { return input.name; }))];
            return groupNames.every(function (name) {
                return step.querySelector('input[name="' + name + '"]:checked');
            });
        }

        function firstUnansweredRadio(step) {
            const requiredRadios = Array.from(step.querySelectorAll('input[type="radio"][required]'));
            const groupNames = [...new Set(requiredRadios.map(function (input) { return input.name; }))];
            const unansweredName = groupNames.find(function (name) {
                return !step.querySelector('input[name="' + name + '"]:checked');
            });
            return unansweredName
                ? step.querySelector('input[name="' + unansweredName + '"][required]')
                : null;
        }

        function renderStep() {
            steps.forEach(function (step, index) {
                const isActive = index === activeStep;
                step.hidden = !isActive;
                step.classList.toggle('active', isActive);
            });

            const current = activeStep + 1;
            const percent = Math.round((current / steps.length) * 100);
            stepLabel.textContent = 'Step ' + current + ' of ' + steps.length;
            percentLabel.textContent = percent + '%';
            progressBar.style.width = percent + '%';
            backButton.disabled = activeStep === 0;
            nextButton.hidden = activeStep === steps.length - 1;
            submitButton.hidden = activeStep !== steps.length - 1;
            errorMessage.hidden = true;
        }

        nextButton.addEventListener('click', function () {
            if (!stepIsComplete(steps[activeStep])) {
                errorMessage.textContent = steps[activeStep].classList.contains('path-question')
                    ? 'Please answer all five questions before continuing.'
                    : 'Choose an answer before continuing.';
                errorMessage.hidden = false;
                const firstUnanswered = firstUnansweredRadio(steps[activeStep]);
                if (firstUnanswered) firstUnanswered.focus();
                return;
            }
            if (activeStep < steps.length - 1) {
                activeStep++;
                renderStep();
            }
        });

        backButton.addEventListener('click', function () {
            if (activeStep > 0) {
                activeStep--;
                renderStep();
            }
        });

        form.addEventListener('submit', function (event) {
            const incompleteIndex = steps.findIndex(function (step) { return !stepIsComplete(step); });
            if (incompleteIndex !== -1) {
                event.preventDefault();
                activeStep = incompleteIndex;
                renderStep();
                errorMessage.textContent = steps[incompleteIndex].classList.contains('path-question')
                    ? 'Please answer all five questions before continuing.'
                    : 'Choose an answer before continuing.';
                errorMessage.hidden = false;
                const firstUnanswered = firstUnansweredRadio(steps[incompleteIndex]);
                if (firstUnanswered) firstUnanswered.focus();
            }
        });

        // If the server returned an error, take the learner to the first unanswered step.
        const firstIncomplete = steps.findIndex(function (step) { return !stepIsComplete(step); });
        activeStep = firstIncomplete === -1 ? 0 : firstIncomplete;
        renderStep();

    });

</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
