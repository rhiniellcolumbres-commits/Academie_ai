<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (isset($_SESSION['user_id'])) {
    
    header('Location: dashboard');
    
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Academie</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/index.css">
</head>

<body>

<?php require_once __DIR__ . '/includes/public-navbar.php'; ?>

<section class="hero-section">
    <div class="container">
        <div class="hero-grid">
            <div>
                <span class="eyebrow">AI-POWERED STUDENT ROADMAP</span>
                <h1 class="hero-title">Build your own <span>learning path.</span></h1>
                <p class="hero-description"><span class="brand-name"><span class="brand-acad">Acad</span><span class="brand-amie">emie</span></span> uses a short IT knowledge pre-test to find your strengths, identify topics to build, recommend a technology path, and create a personalized roadmap.</p>
                <div class="hero-actions">
                    <a href="register" class="btn btn-primary btn-lg hero-btn-primary"><i class="bi bi-rocket-takeoff" aria-hidden="true"></i> Get started free</a>
                    <a href="login" class="btn btn-outline-primary btn-lg hero-btn-secondary">I already have an account</a>
                </div>
                <ul class="hero-points">
                    <li><i class="bi bi-check-circle-fill" aria-hidden="true"></i>IT Knowledge pre-test</li>
                    <li><i class="bi bi-check-circle-fill" aria-hidden="true"></i> AI-built personalized roadmap</li>
                    <li><i class="bi bi-check-circle-fill" aria-hidden="true"></i> Lessons and activities per topic</li>
                </ul>
            </div>

            <div class="roadmap-wrap">
                <div class="roadmap-preview">
                    <div class="preview-header">
                        <div>
                            <small>PERSONALIZED ROADMAP</small>
                            <h4>Your Learning Journey</h4>
                        </div>
                        <span class="preview-badge"><i class="bi bi-mortarboard" aria-hidden="true"></i><small>Personalized Path</small></span>
                    </div>
                    <div class="roadmap-line">
                        <div class="roadmap-item completed"><div class="roadmap-dot">✓</div><div><strong>IT Foundations</strong><small>Build a clear base in core technology concepts.</small></div><span class="roadmap-status completed-status">Completed</span></div>
                        <div class="roadmap-item completed"><div class="roadmap-dot">✓</div><div><strong>Core Concepts &amp; Tools</strong><small>Learn the ideas and tools used in your recommended path.</small></div><span class="roadmap-status completed-status">Completed</span></div>
                        <div class="roadmap-item current"><div class="roadmap-dot">3</div><div><strong>Practice &amp; Application</strong><small>Apply what you learn through guided examples and hands-on tasks.</small></div><span class="roadmap-status current-status">Current</span></div>
                        <div class="roadmap-item"><div class="roadmap-dot">4</div><div><strong>Portfolio Project</strong><small>Build a project that demonstrates your growing skills.</small></div><span class="roadmap-status upcoming-status">Upcoming</span></div>
                        <div class="roadmap-item"><div class="roadmap-dot">5</div><div><strong>Career Readiness</strong><small>Prepare to explain and extend the work you have completed.</small></div><span class="roadmap-status upcoming-status">Upcoming</span></div>
                    </div>
                    <div class="preview-progress">
                        <div class="preview-progress-row"><span>ROADMAP PROGRESS</span><span>62%</span></div>
                        <div class="preview-progress-bar"><span></span></div>
                    </div>
                </div>
                <div class="floating-card">Current focus<strong>Practice &amp; Application</strong></div>
            </div>
        </div>
    </div>
</section>

<section class="features-section">

    <div class="container">

        <div class="section-heading">

            <span class="eyebrow">
                HOW IT WORKS
            </span>

            <h2>
                Your learning journey, personalized.
            </h2>

            <p class="text-secondary">
               <span class="brand-name"><span class="brand-acad">Acad</span><span class="brand-amie">emie</span></span> helps you understand what to learn
                and where to go next.
            </p>

        </div>

<div class="features-grid">

            <div>
                <div class="feature-card">

                    <div class="feature-number">
                        01
                    </div>

                    <h4>
                        Take the Assessment
                    </h4>

                    <p>
                        Answer questions about core IT topics. Your results show what you know and where you can grow.
                    </p>

                </div>

            </div>

<div>
                <div class="feature-card">

                    <div class="feature-number">
                        02
                    </div>

                    <h4>
                        Get Your Roadmap
                    </h4>

                    <p>
                        AI uses your pre-test results to recommend a technology path and create a personalized roadmap.
                    </p>

                </div>

            </div>

<div>
                <div class="feature-card">

                    <div class="feature-number">
                        03
                    </div>

                    <h4>
                        Track Your Progress
                    </h4>

                    <p>
                        Follow your roadmap, complete topics,
                        and monitor your learning progress.
                    </p>

                </div>

            </div>

        </div>

    </div>

</section>

<section class="tracks-section">
    <div class="container">
        <div class="section-heading">
            <span class="eyebrow">CAREER TRACKS</span>
            <h2>Find the tech path that fits you.</h2>
            <p class="text-secondary">Take the knowledge pre-test to get matched with a technology track and its step-by-step roadmap.</p>
        </div>
        <div class="tracks-grid">
            <div class="track-card"><span class="track-icon"><i class="bi bi-braces" aria-hidden="true"></i></span><h5>Software Engineering</h5><p>Core programming, algorithms, version control and building real software.</p></div>
            <div class="track-card"><span class="track-icon"><i class="bi bi-layers" aria-hidden="true"></i></span><h5>Full-Stack Development</h5><p>Build complete web apps from the interface to the server and database.</p></div>
            <div class="track-card"><span class="track-icon"><i class="bi bi-window-stack" aria-hidden="true"></i></span><h5>Frontend Web Development</h5><p>HTML, CSS, JavaScript and modern frameworks for responsive interfaces.</p></div>
            <div class="track-card"><span class="track-icon"><i class="bi bi-hdd-network" aria-hidden="true"></i></span><h5>Backend Development</h5><p>APIs, databases, authentication and the logic that powers applications.</p></div>
            <div class="track-card"><span class="track-icon"><i class="bi bi-palette" aria-hidden="true"></i></span><h5>UI/UX Design</h5><p>Research, wireframing and designing experiences people enjoy using.</p></div>
            <div class="track-card"><span class="track-icon"><i class="bi bi-controller" aria-hidden="true"></i></span><h5>Game Development</h5><p>Game logic, engines and design to bring your own game ideas to life.</p></div>
            <div class="track-card"><span class="track-icon"><i class="bi bi-shield-lock" aria-hidden="true"></i></span><h5>Cybersecurity</h5><p>Networks, secure systems and practical ways to protect data and users.</p></div>
            <div class="track-card track-card-cta"><h5>Not sure where to start?</h5><p>Your pre-test results can help recommend a track based on what you know today.</p><a href="register">Take the knowledge pre-test <i class="bi bi-arrow-right" aria-hidden="true"></i></a></div>
        </div>
    </div>
</section>

<section class="benefits-section">
    <div class="container">
        <div class="benefits-grid">
            <div class="benefits-intro">
                <span class="eyebrow">WHY ACADEMIE</span>
                <h2>Everything you need to keep learning, in one place.</h2>
                <p>Stop guessing what to study next. Academie turns your pre-test results into a clear plan and walks with you through every stage.</p>
                <a href="register" class="btn btn-primary px-4">Create your account</a>
            </div>
            <div class="benefit-list">
                <div class="benefit-item"><span class="benefit-icon"><i class="bi bi-signpost-split" aria-hidden="true"></i></span><div><h5>A clear, staged roadmap</h5><p>Build from your assessed starting point with topics, activities and a concrete result at each stage.</p></div></div>
                <div class="benefit-item"><span class="benefit-icon"><i class="bi bi-stars" aria-hidden="true"></i></span><div><h5>AI lessons on demand</h5><p>Open any topic to get a guided lesson with an example, a practice task and a checklist.</p></div></div>
                <div class="benefit-item"><span class="benefit-icon"><i class="bi bi-graph-up-arrow" aria-hidden="true"></i></span><div><h5>Progress you can see</h5><p>Tick off activities and topics and watch your dashboard update as you go.</p></div></div>
                <div class="benefit-item"><span class="benefit-icon"><i class="bi bi-trophy" aria-hidden="true"></i></span><div><h5>Achievements that motivate</h5><p>Earn badges as you finish activities and stages to keep your momentum up.</p></div></div>
            </div>
        </div>
    </div>
</section>

<section class="faq-section">
    <div class="container">
        <div class="section-heading">
            <span class="eyebrow">FAQ</span>
            <h2>Questions, answered.</h2>
        </div>
        <div class="accordion faq-accordion" id="homeFaq">
            <div class="accordion-item"><h3 class="accordion-header"><button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#q1" aria-expanded="true">Who is Academie for?</button></h3><div id="q1" class="accordion-collapse collapse show" data-bs-parent="#homeFaq"><div class="accordion-body">Students and beginners who want to build IT knowledge, find a suitable technology path, and follow a structured learning roadmap.</div></div></div>
            <div class="accordion-item"><h3 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#q2" aria-expanded="false">How is my roadmap created?</button></h3><div id="q2" class="accordion-collapse collapse" data-bs-parent="#homeFaq"><div class="accordion-body">You take a short IT knowledge pre-test. We use your score and topic results to estimate your starting point, recommend a technology path, and generate a roadmap that addresses your learning needs.</div></div></div>
            <div class="accordion-item"><h3 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#q3" aria-expanded="false">Do I need any experience?</button></h3><div id="q3" class="accordion-collapse collapse" data-bs-parent="#homeFaq"><div class="accordion-body">No. The pre-test estimates your starting point, and your roadmap begins at a level suited to your results. Beginners can start with fundamentals, while experienced learners can move into more advanced work.</div></div></div>
            <div class="accordion-item"><h3 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#q4" aria-expanded="false">Is my progress saved?</button></h3><div id="q4" class="accordion-collapse collapse" data-bs-parent="#homeFaq"><div class="accordion-body">Yes. Your pre-test results, roadmap, completed topics and achievements are saved to your account, so you can pick up where you left off.</div></div></div>
            <div class="accordion-item"><h3 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#q5" aria-expanded="false">Are the AI lessons always accurate?</button></h3><div id="q5" class="accordion-collapse collapse" data-bs-parent="#homeFaq"><div class="accordion-body">AI-generated content is meant as educational guidance and may need verification. Use the suggested resources alongside the lessons to double-check what you learn.</div></div></div>
        </div>
    </div>
</section>

<section class="cta-section">

    <div class="container text-center">

        <h2>
            Ready to build your learning path?
        </h2>

        <p>
            Start your personalized journey today.
        </p>

             <a
    href="register"
    class="btn btn-light btn-lg px-4"   
            >
                Get Started
            </a>

    </div>

</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
