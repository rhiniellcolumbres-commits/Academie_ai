<?php

require_once __DIR__ . '/ai.php';

// Escapes text for safe HTML output.
function e($value)
{
    
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// Stops the request unless it uses POST.
function require_post()
{
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        
        http_response_code(405);
        exit('Method Not Allowed');
    }
}

// Redirects the browser to another page.
function redirect($location)
{
    
    header('Location: ' . $location);
    
    exit;
}

// Fetches a user and their latest assessment details.
function getUserById($pdo, $userId)
{
    
    $stmt = $pdo->prepare(
        'SELECT
            u.*,
            a.career_goal,
            a.current_level,
            a.improvement_goal,
            a.interests,
            a.skills,
            a.id AS assessment_id
         FROM users u
         LEFT JOIN assessments a ON a.user_id = u.id
         WHERE u.id = ?
         LIMIT 1'
    );

$stmt->execute([$userId]);

return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Trims text and limits its length before saving.
function clean_text($value, $maxLength = 255)
{
    
    if (!is_string($value)) {
        return '';
    }

$value = trim($value);

if (mb_strlen($value) > $maxLength) {
        $value = mb_substr($value, 0, $maxLength);
    }

return $value;
}

// Splits comma separated text into a list.
function split_list($value)
{
    
    if (!is_string($value) || trim($value) === '') {
        return [];
    }

$items = explode(',', $value);
    $cleanItems = [];

    foreach ($items as $item) {
        $item = trim($item);

if ($item !== '' && $item !== '0') {
            $cleanItems[] = $item;
        }
    }

    return $cleanItems;
}

// Converts saved activity data into a list.
function getActivities($activity)
{
    
    if (is_string($activity)) {
        if (trim($activity) === '') {
            return [];
        }

        $decoded = json_decode($activity, true);

if (is_array($decoded)) {
            $activity = $decoded;
        } else {
            
            return [trim($activity)];
        }
    }

if (!is_array($activity)) {
        return [];
    }

    $validActivities = [];

foreach ($activity as $item) {
        if (is_string($item) && trim($item) !== '') {
            $validActivities[] = $item;
        }
    }

    return $validActivities;
}

// Counts completed roadmap stages and activities.
function getRoadmapProgress(array $topics)
{
    
    $progress = [
        'totalStages'         => count($topics),
        'completedStages'     => 0,
        'totalActivities'     => 0,
        'completedActivities' => 0,
    ];

foreach ($topics as $topic) {
        
        if ((int)$topic['completed'] === 1) {
            $progress['completedStages']++;
        }

$activities = getActivities($topic['activity'] ?? '');
        $states = json_decode((string)($topic['activity_progress'] ?? ''), true);

        if (!is_array($states)) {
            $states = [];
        }

$progress['totalActivities'] += count($activities);

foreach (array_keys($activities) as $index) {
            if (!empty($states[$index])) {
                $progress['completedActivities']++;
            }
        }
    }

    return $progress;
}

// Returns the available achievement details.
function getAchievementCatalog()
{
    return [
        'first_steps'      => ['icon' => 'bi bi-seedling', 'title' => 'First Steps',       'description' => 'Completed your first learning activity.'],
        'stage_achiever'   => ['icon' => 'bi bi-award', 'title' => 'Stage Achiever',    'description' => 'Completed your first roadmap stage.'],
        'halfway_hero'     => ['icon' => 'bi bi-lightning-charge', 'title' => 'Halfway Hero',      'description' => 'Reached the halfway milestone of your roadmap.'],
        'roadmap_champion' => ['icon' => 'bi bi-trophy', 'title' => 'Roadmap Champion',  'description' => 'Completed every stage in your current roadmap.'],
        'activity_master'  => ['icon' => 'bi bi-star', 'title' => 'Activity Master',   'description' => 'Completed every activity in your current roadmap.'],
    ];
}

// Saves achievements earned from the user progress.
function syncAchievements($pdo, $userId, array $progress)
{
    
    $earned = [];

if ($progress['completedActivities'] >= 1) {
        $earned[] = 'first_steps';
    }
    
    if ($progress['completedStages'] >= 1) {
        $earned[] = 'stage_achiever';
    }
    
    if ($progress['totalStages'] > 0
        && $progress['completedStages'] >= (int)ceil($progress['totalStages'] / 2)) {
        $earned[] = 'halfway_hero';
    }
    
    if ($progress['totalStages'] > 0
        && $progress['completedStages'] === $progress['totalStages']) {
        $earned[] = 'roadmap_champion';
    }
    
    if ($progress['totalActivities'] > 0
        && $progress['completedActivities'] === $progress['totalActivities']) {
        $earned[] = 'activity_master';
    }

if (!$earned) {
        return;
    }

$titleStmt = $pdo->prepare('SELECT title FROM study_paths WHERE user_id = ? LIMIT 1');
    $titleStmt->execute([$userId]);
    $roadmapTitle = (string)($titleStmt->fetchColumn() ?: 'Learning Roadmap');

$saveStmt = $pdo->prepare(
        'INSERT IGNORE INTO user_achievements
            (user_id, achievement_code, title, description, icon, roadmap_title)
         VALUES (?, ?, ?, ?, ?, ?)'
    );

$catalog = getAchievementCatalog();

foreach ($earned as $code) {
        $badge = $catalog[$code];
        $saveStmt->execute([
            $userId, $code, $badge['title'], $badge['description'], $badge['icon'], $roadmapTitle
        ]);
    }
}

// Fetches a user’s saved achievements.
function getUserAchievements($pdo, $userId)
{
    $stmt = $pdo->prepare(
        'SELECT title, description, icon, roadmap_title, earned_at
         FROM user_achievements
         WHERE user_id = ?
         ORDER BY earned_at DESC, id DESC'
    );
    $stmt->execute([$userId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Creates one roadmap stage with the standard fields.
function roadmap_stage(
    $category,
    $title,
    $description,
    $topics,
    $activities,
    $expectedOutput,
    $resources
) {
    return [
        'category' => $category,
        'title' => $title,
        'description' => $description,
        'topics' => $topics,
        'activity' => $activities,
        'expected_output' => $expectedOutput,
        'resources' => $resources
    ];
}

// Builds a fallback roadmap from the user assessment.
function generate_study_topics($user)
{
    $career = trim(
        (string)($user['career_goal'] ?? '')
    );

    $level = trim(
        (string)($user['current_level'] ?? 'Beginner')
    );

    $goal = trim(
        (string)($user['improvement_goal'] ?? '')
    );

$roadmaps = [

        'Frontend Developer' => [

            roadmap_stage(
                'FOUNDATION',
                'Web Fundamentals',
                'Learn the basic building blocks of websites and understand how the web works.',
                'HTML, CSS, Web Basics',

                [
                    'Learn how HTML documents are structured.',
                    'Practice creating headings, paragraphs, links, and images.',
                    'Learn how HTML elements are organized using containers and semantic elements.',
                    'Create a webpage with a header, main content, and footer.',
                    'Practice connecting multiple HTML pages using navigation links.',
                    'Create a simple personal profile webpage using HTML.'
                ],

                'A working HTML webpage containing basic content and navigation.',

                'MDN HTML Guide, HTML Elements Reference, freeCodeCamp HTML'
            ),

roadmap_stage(
                'STYLING',
                'Responsive Web Design',
                'Create clean and responsive layouts that work across different screen sizes.',
                'CSS, Flexbox, Grid, Responsive Design',

                [
                    'Learn CSS selectors, properties, and values.',
                    'Practice styling text, backgrounds, borders, and spacing.',
                    'Learn how the CSS box model works.',
                    'Practice creating layouts using Flexbox.',
                    'Practice creating layouts using CSS Grid.',
                    'Build a responsive landing page that works on desktop and mobile.'
                ],

                'A responsive webpage that works properly on desktop and mobile.',

                'MDN CSS Guide, CSS Flexbox Guide, CSS Grid Guide'
            ),

roadmap_stage(
                'PROGRAMMING',
                'JavaScript Fundamentals',
                'Learn programming logic and use JavaScript to make websites interactive.',
                'Variables, Conditions, Functions, Arrays, DOM',

                [
                    'Learn JavaScript variables and basic data types.',
                    'Practice using operators and expressions.',
                    'Learn conditional statements and comparison operators.',
                    'Practice loops and arrays.',
                    'Create reusable JavaScript functions.',
                    'Build a simple interactive JavaScript application.'
                ],

                'A working JavaScript application with user interaction.',

                'MDN JavaScript Guide, JavaScript.info, freeCodeCamp JavaScript'
            ),

roadmap_stage(
                'ADVANCED',
                'Modern Frontend Development',
                'Learn modern JavaScript development techniques and work with APIs.',
                'ES6, REST APIs, JSON, Async JavaScript',

                [
                    'Learn modern JavaScript features such as let, const, and arrow functions.',
                    'Practice working with objects and destructuring.',
                    'Learn JSON and how web applications exchange data.',
                    'Learn how HTTP requests work.',
                    'Use the Fetch API to retrieve data.',
                    'Create a webpage that displays information from a public REST API.'
                ],

                'A working webpage that fetches and displays API data.',

                'MDN Fetch API, REST API Introduction, JSON Guide'
            ),

roadmap_stage(
                'FRAMEWORK',
                'Frontend Framework',
                'Learn a modern frontend framework and build component-based applications.',
                'React, Components, State, Routing',

                [
                    'Learn why frontend frameworks are used.',
                    'Learn the basic structure of React applications.',
                    'Create reusable React components.',
                    'Practice passing data using props.',
                    'Learn basic state management and events.',
                    'Build a small interactive React application.'
                ],

                'A functional component-based frontend application.',

                'React Documentation, React Learn, Vite Documentation'
            ),

roadmap_stage(
                'PROJECT',
                'Build Real Projects',
                'Create practical projects that demonstrate your frontend development skills.',
                'Portfolio, Projects, Git, Deployment',

                [
                    'Choose a practical frontend project idea.',
                    'Plan the pages and features of the project.',
                    'Build the main user interface.',
                    'Add JavaScript functionality and interactions.',
                    'Test the project on different screen sizes.',
                    'Publish the completed project to GitHub or a hosting platform.'
                ],

                'A complete frontend project hosted in a GitHub repository.',

                'GitHub Docs, Git Documentation, Vercel Documentation'
            ),

roadmap_stage(
                'CAREER',
                'Become Job Ready',
                'Prepare your portfolio, GitHub profile, resume, and interview skills.',
                'GitHub, Portfolio, Resume, Interviews',

                [
                    'Organize your GitHub repositories.',
                    'Choose your strongest projects for your portfolio.',
                    'Write clear descriptions for your projects.',
                    'Create or update your developer resume.',
                    'Practice explaining your technical decisions.',
                    'Prepare answers for common frontend developer interview questions.'
                ],

                'A professional portfolio, organized GitHub profile, and updated resume.',

                'GitHub Profile Guide, Resume Guide, Frontend Interview Preparation'
            )
        ],

'Backend Developer' => [

            roadmap_stage(
                'FOUNDATION',
                'Programming Fundamentals',
                'Build a strong foundation in programming logic and problem solving.',
                'Variables, Conditions, Loops, Functions',

                [
                    'Learn variables and common programming data types.',
                    'Practice arithmetic, comparison, and logical operators.',
                    'Learn conditional statements.',
                    'Practice loops and repetition.',
                    'Create reusable functions.',
                    'Build a small program that combines these programming concepts.'
                ],

                'A working program demonstrating basic programming logic.',

                'freeCodeCamp Programming, MDN JavaScript Guide, W3Schools Programming'
            ),

roadmap_stage(
                'PROGRAMMING',
                'Backend Programming',
                'Learn server-side programming and understand how backend applications process requests.',
                'PHP, Python, Java, Server-side Programming',

                [
                    'Understand the difference between frontend and backend development.',
                    'Learn how a server processes HTTP requests.',
                    'Create a basic server-side application.',
                    'Practice receiving form data on the server.',
                    'Process and validate user input.',
                    'Build a small server-side application that processes submitted information.'
                ],

                'A working server-side application that processes user input.',

                'PHP Documentation, Python Documentation, MDN Server-side Web Development'
            ),

roadmap_stage(
                'DATABASE',
                'Databases & SQL',
                'Learn how applications store, retrieve, and manage data.',
                'SQL, MySQL, Database Design, CRUD',

                [
                    'Learn tables, rows, columns, and primary keys.',
                    'Create a MySQL database.',
                    'Practice INSERT and SELECT queries.',
                    'Practice UPDATE and DELETE queries.',
                    'Learn relationships between database tables.',
                    'Build a CRUD application connected to MySQL.'
                ],

                'A working CRUD application connected to MySQL.',

                'MySQL Documentation, SQLBolt, W3Schools SQL'
            ),

roadmap_stage(
                'API',
                'REST APIs',
                'Learn how applications communicate through APIs and exchange data.',
                'REST, JSON, HTTP, API Endpoints',

                [
                    'Learn what an API is and why applications use APIs.',
                    'Understand HTTP methods such as GET, POST, PUT, and DELETE.',
                    'Learn how JSON data is structured.',
                    'Create a basic GET endpoint.',
                    'Create endpoints that modify application data.',
                    'Build a REST API that returns structured JSON responses.'
                ],

                'A working REST API returning structured JSON responses.',

                'MDN HTTP, REST API Guide, Postman Learning Center'
            ),

roadmap_stage(
                'SECURITY',
                'Authentication & Security',
                'Learn how to protect applications, users, and sensitive data.',
                'Sessions, JWT, OAuth, Password Security',

                [
                    'Learn common web application security risks.',
                    'Learn how password hashing works.',
                    'Implement secure user registration.',
                    'Implement login and logout functionality.',
                    'Learn how sessions and authentication tokens work.',
                    'Protect authenticated application routes.'
                ],

                'A backend application with working and protected authentication.',

                'OWASP Authentication Cheat Sheet, PHP Password Hashing, JWT Documentation'
            ),

roadmap_stage(
                'PROJECT',
                'Build Backend Projects',
                'Build a complete backend application connected to a database and API.',
                'Backend Project, Database, REST API',

                [
                    'Choose a backend project idea.',
                    'Design the database structure.',
                    'Create the backend application.',
                    'Implement CRUD operations.',
                    'Add API endpoints and authentication.',
                    'Test and document the completed backend project.'
                ],

                'A complete backend project with database integration and API endpoints.',

                'GitHub Docs, MySQL Documentation, Postman'
            ),

roadmap_stage(
                'CAREER',
                'Become Job Ready',
                'Prepare your portfolio and develop the skills needed for backend development jobs.',
                'GitHub, Portfolio, Resume, Interviews',

                [
                    'Organize your backend projects on GitHub.',
                    'Write documentation for your projects.',
                    'Review SQL and backend programming concepts.',
                    'Create a backend-focused resume.',
                    'Practice explaining your API and database design.',
                    'Practice common backend developer interview questions.'
                ],

                'A professional GitHub profile, portfolio projects, and developer resume.',

                'GitHub Docs, Backend Interview Questions, Resume Guide'
            )
        ],

'Full-Stack Developer' => [

            roadmap_stage(
                'FOUNDATION',
                'Web Fundamentals',
                'Understand how websites, browsers, servers, and the web work.',
                'HTML, CSS, HTTP, Web Basics',

                [
                    'Learn how websites are structured using HTML.',
                    'Practice basic CSS styling.',
                    'Understand how browsers communicate with servers.',
                    'Learn the basics of HTTP requests and responses.',
                    'Understand the difference between frontend and backend.',
                    'Create a simple webpage and explain the client-server process.'
                ],

                'A basic webpage and a clear understanding of the client-server process.',

                'MDN Web Docs, HTTP Overview, How the Web Works'
            ),

roadmap_stage(
                'FRONTEND',
                'Frontend Development',
                'Learn how to create responsive and interactive user interfaces.',
                'HTML, CSS, JavaScript, Responsive Design',

                [
                    'Create semantic HTML page structures.',
                    'Style pages using CSS.',
                    'Practice responsive layouts.',
                    'Learn JavaScript fundamentals.',
                    'Add JavaScript interactions to webpages.',
                    'Build a complete responsive frontend application.'
                ],

                'A responsive and interactive frontend application.',

                'MDN HTML, MDN CSS, MDN JavaScript'
            ),

roadmap_stage(
                'BACKEND',
                'Backend Development',
                'Learn server-side programming and how applications process requests.',
                'PHP, Python, Node.js, Server-side Programming',

                [
                    'Understand server-side application architecture.',
                    'Create a basic backend application.',
                    'Practice receiving HTTP requests.',
                    'Process and validate submitted data.',
                    'Return useful responses from the server.',
                    'Build a working server-side application.'
                ],

                'A working server-side application.',

                'PHP Documentation, Node.js Documentation, MDN'
            ),

roadmap_stage(
                'DATABASE',
                'Databases',
                'Learn how to design databases and connect them to applications.',
                'SQL, MySQL, Database Design, CRUD',

                [
                    'Learn database tables and relationships.',
                    'Create a MySQL database.',
                    'Practice writing SQL queries.',
                    'Implement CRUD operations.',
                    'Connect the database to a backend application.',
                    'Build a working database-driven application.'
                ],

                'A properly structured database connected to an application.',

                'MySQL Documentation, SQLBolt, Database Design Guide'
            ),

roadmap_stage(
                'API',
                'REST APIs',
                'Connect frontend and backend systems using APIs.',
                'REST, JSON, HTTP, API Integration',

                [
                    'Learn REST API concepts.',
                    'Understand GET, POST, PUT, and DELETE requests.',
                    'Learn how JSON is used between applications.',
                    'Create backend API endpoints.',
                    'Connect a frontend application to the API.',
                    'Display API data inside the frontend application.'
                ],

                'A full frontend-to-backend API integration.',

                'MDN Fetch API, Postman, REST API Guide'
            ),

roadmap_stage(
                'SECURITY',
                'Authentication & Security',
                'Learn secure authentication and common web application security practices.',
                'JWT, OAuth, Sessions, Security',

                [
                    'Learn common web application vulnerabilities.',
                    'Implement secure password hashing.',
                    'Create user registration.',
                    'Create login and logout functionality.',
                    'Learn JWT or OAuth authentication.',
                    'Protect authenticated pages and API endpoints.'
                ],

                'A full-stack application with secure authentication.',

                'OWASP, JWT Documentation, OAuth Documentation'
            ),

roadmap_stage(
                'PROJECT',
                'Full-Stack Projects',
                'Build complete applications from frontend to backend and database.',
                'Full-Stack Project, Git, Deployment',

                [
                    'Choose a full-stack project idea.',
                    'Design the frontend and database.',
                    'Build the frontend interface.',
                    'Build the backend and database.',
                    'Connect the frontend and backend through an API.',
                    'Deploy and document the completed application.'
                ],

                'A deployed full-stack application available in GitHub.',

                'GitHub Docs, Deployment Documentation, Project Architecture Guides'
            ),

roadmap_stage(
                'CAREER',
                'Become Job Ready',
                'Build a professional portfolio and prepare for full-stack development jobs.',
                'GitHub, Portfolio, Resume, Interviews',

                [
                    'Organize your full-stack projects on GitHub.',
                    'Document your best projects.',
                    'Prepare a developer portfolio.',
                    'Create a full-stack developer resume.',
                    'Practice explaining your application architecture.',
                    'Practice common full-stack developer interview questions.'
                ],

                'A professional portfolio, resume, GitHub profile, and interview preparation.',

                'GitHub, Developer Resume Guide, Full-Stack Interview Preparation'
            )
        ]

    ];

$roadmaps['Data Analyst'] = [

        roadmap_stage(
            'FOUNDATION',
            'Data Fundamentals',
            'Understand data, data types, spreadsheets, and basic analytical thinking.',
            'Data Types, Excel, Google Sheets, Data Concepts',

            [
                'Learn what data is and why organizations use it.',
                'Learn the difference between qualitative and quantitative data.',
                'Practice organizing information in a spreadsheet.',
                'Calculate totals, averages, percentages, and basic statistics.',
                'Create simple tables and charts from sample data.',
                'Complete a small spreadsheet analysis using a sample dataset.'
            ],

            'A spreadsheet demonstrating basic data analysis.',

            'Microsoft Excel Training, Google Sheets Training, Data Analysis Basics'
        ),

        roadmap_stage(
            'SQL',
            'SQL & Databases',
            'Learn how to retrieve, filter, and analyze data from databases.',
            'SQL, MySQL, Queries, Joins',

            [
                'Learn database tables, rows, columns, and primary keys.',
                'Practice SELECT statements.',
                'Practice filtering and sorting data.',
                'Learn GROUP BY and aggregate functions.',
                'Learn how SQL JOIN operations work.',
                'Write SQL queries that answer practical data questions.'
            ],

            'A collection of SQL queries that answer practical questions.',

            'SQLBolt, MySQL Documentation, W3Schools SQL'
        ),

        roadmap_stage(
            'PYTHON',
            'Python for Data Analysis',
            'Use Python to work with and analyze datasets.',
            'Python, Pandas, DataFrames',

            [
                'Learn Python variables and basic data types.',
                'Practice Python lists, dictionaries, and functions.',
                'Learn how to install and use data analysis libraries.',
                'Load a dataset using Pandas.',
                'Filter and manipulate DataFrame data.',
                'Perform a basic analysis using Python and Pandas.'
            ],

            'A Python notebook containing data analysis.',

            'Python Documentation, Pandas Documentation, Kaggle Learn'
        ),

        roadmap_stage(
            'CLEANING',
            'Data Cleaning',
            'Learn how to prepare datasets and handle missing or incorrect information.',
            'Data Cleaning, Missing Data, Transformation',

            [
                'Inspect a dataset for missing information.',
                'Identify duplicate records.',
                'Find inconsistent values and formatting.',
                'Practice handling missing values.',
                'Transform data into a consistent format.',
                'Create a cleaned dataset and document the changes made.'
            ],

            'A cleaned and documented dataset.',

            'Pandas Documentation, Kaggle Learn'
        ),

        roadmap_stage(
            'VISUALIZATION',
            'Data Visualization',
            'Create visualizations that communicate useful information clearly.',
            'Charts, Dashboards, Visualization',

            [
                'Learn when to use different chart types.',
                'Create basic charts from a dataset.',
                'Practice labeling charts correctly.',
                'Learn how dashboards organize information.',
                'Create a dashboard containing useful metrics.',
                'Present important insights using your visualizations.'
            ],

            'A data visualization dashboard.',

            'Power BI Learning, Tableau Learning, Matplotlib Documentation'
        ),

        roadmap_stage(
            'PROJECT',
            'Data Analysis Project',
            'Complete a real-world data analysis project and present your findings.',
            'Dataset, Analysis, Dashboard, Presentation',

            [
                'Choose a public dataset.',
                'Define questions that you want the data to answer.',
                'Clean and prepare the dataset.',
                'Analyze the dataset using SQL or Python.',
                'Create charts or a dashboard showing your findings.',
                'Write and present a short report explaining the results.'
            ],

            'A complete data analysis project and presentation.',

            'Kaggle, Power BI, GitHub'
        ),

        roadmap_stage(
            'CAREER',
            'Become Job Ready',
            'Build a data portfolio and prepare for data analyst opportunities.',
            'Portfolio, Resume, GitHub, Interviews',

            [
                'Organize your data analysis projects.',
                'Document your strongest projects.',
                'Create a data analyst portfolio.',
                'Prepare a resume focused on analytical skills.',
                'Practice explaining your analysis and findings.',
                'Practice common data analyst interview questions.'
            ],

            'A data analyst portfolio and professional resume.',

            'GitHub, Data Analyst Resume Guide, Interview Preparation'
        )
    ];

$roadmaps['Cybersecurity'] = [

        roadmap_stage(
            'FOUNDATION',
            'Cybersecurity Fundamentals',
            'Understand the basic concepts of cybersecurity and information security.',
            'CIA Triad, Threats, Security Principles',

            [
                'Learn the meaning of confidentiality, integrity, and availability.',
                'Learn common cybersecurity threats.',
                'Understand the difference between vulnerabilities and threats.',
                'Learn basic security principles.',
                'Analyze security risks in a simple application.',
                'Create a basic security assessment for a sample application.'
            ],

            'A basic security assessment document.',

            'OWASP, CISA Cybersecurity Resources'
        ),

        roadmap_stage(
            'NETWORK',
            'Networking Fundamentals',
            'Learn how networks communicate and where security risks can occur.',
            'TCP/IP, DNS, HTTP, Networking',

            [
                'Learn the basics of computer networks.',
                'Understand IP addresses and ports.',
                'Learn how DNS works.',
                'Understand HTTP and HTTPS.',
                'Learn how computers communicate with web servers.',
                'Create a diagram explaining a basic web communication process.'
            ],

            'A network communication diagram with explanations.',

            'Cisco Networking Basics, MDN HTTP'
        ),

        roadmap_stage(
            'SYSTEMS',
            'Operating Systems',
            'Understand operating systems and basic system security.',
            'Linux, Windows, Processes, Permissions',

            [
                'Learn the purpose of an operating system.',
                'Set up a Linux practice environment.',
                'Practice basic Linux terminal commands.',
                'Learn users, groups, and file permissions.',
                'Practice managing files and processes.',
                'Document your Linux security practice environment.'
            ],

            'A documented Linux practice environment.',

            'Linux Documentation, Ubuntu Documentation'
        ),

        roadmap_stage(
            'SECURITY',
            'Security Fundamentals',
            'Learn common attacks, vulnerabilities, and defensive techniques.',
            'Malware, Phishing, Vulnerabilities, Firewalls',

            [
                'Learn what malware is and how it can affect systems.',
                'Learn how phishing attacks work.',
                'Study common application vulnerabilities.',
                'Learn the purpose of firewalls.',
                'Analyze example security incidents.',
                'Create a threat and defense analysis document.'
            ],

            'A documented threat and defense analysis.',

            'CISA, OWASP, Cybersecurity Fundamentals'
        ),

        roadmap_stage(
            'WEB',
            'Web Application Security',
            'Understand common web vulnerabilities and secure development practices.',
            'OWASP, XSS, SQL Injection, Authentication',

            [
                'Learn the OWASP Top 10.',
                'Understand how input validation helps prevent attacks.',
                'Learn the basic concept of cross-site scripting.',
                'Learn the basic concept of SQL injection.',
                'Study secure authentication practices.',
                'Review a deliberately vulnerable application in a controlled lab and document the issues found.'
            ],

            'A security testing report from a controlled lab.',

            'OWASP Top 10, PortSwigger Web Security Academy'
        ),

        roadmap_stage(
            'PRACTICE',
            'Security Labs & Projects',
            'Practice security concepts through controlled labs and projects.',
            'Security Labs, Vulnerability Testing, Documentation',

            [
                'Choose a beginner-friendly cybersecurity lab.',
                'Complete the lab in a controlled environment.',
                'Document the security concept being demonstrated.',
                'Identify the vulnerability or security weakness.',
                'Document the appropriate defensive technique.',
                'Organize the completed labs into a security portfolio.'
            ],

'A cybersecurity lab portfolio and documentation.',

            'TryHackMe, PortSwigger Web Security Academy, OWASP'
        ),

        roadmap_stage(
            'CAREER',
            'Become Job Ready',
            'Prepare for entry-level cybersecurity roles and build your security portfolio.',
            'Portfolio, Certifications, Resume, Interviews',

            [
                'Organize your cybersecurity projects.',
                'Document your completed security labs.',
                'Create a cybersecurity portfolio.',
                'Prepare a security-focused resume.',
                'Review important cybersecurity concepts.',
                'Practice common cybersecurity interview questions.'
            ],

            'A cybersecurity portfolio and updated resume.',

            'GitHub, Cybersecurity Career Guide, Interview Preparation'
        )
    ];

$careerAliases = [
        'Software Engineering / Core Developer' => 'Backend Developer',
        'Full-Stack Development' => 'Full-Stack Developer',
        'Frontend Web Development' => 'Frontend Developer',
        'Backend Development' => 'Backend Developer',
        'UI/UX Design' => 'Frontend Developer',
        'AI & Machine Learning' => 'Data Analyst',
        'Game Development' => 'Backend Developer'
    ];
    $roadmapKey = $careerAliases[$career] ?? $career;
    $topics = $roadmaps[$roadmapKey]
        ?? $roadmaps['Frontend Developer'];

if (
        $level === 'Complete Beginner'
        || $level === 'Beginner'
    ) {

        foreach ($topics as &$topic) {

            $activities = getActivities(
                $topic['activity']
            );

            foreach ($activities as &$activity) {

                $activity =
                    'Beginner Activity: ' . $activity;
            }

            unset($activity);

            $topic['activity'] = $activities;
        }

        unset($topic);
    }

if ($level === 'Intermediate') {

        foreach ($topics as &$topic) {

            if (
                $topic['category'] === 'FOUNDATION'
            ) {

                $topic['description'] =
                    'Review the essential fundamentals quickly, then apply them through practical exercises. '
                    . $topic['description'];
            }
        }

        unset($topic);
    }

if ($level === 'Advanced') {

        foreach ($topics as &$topic) {

            if (
                $topic['category'] === 'FOUNDATION'
            ) {

                $topic['description'] =
                    'Quickly review the fundamentals and focus on applying them to real-world development problems.';
            }
        }

        unset($topic);
    }

if ($goal === 'Build Real Projects') {

        foreach ($topics as &$topic) {

            $activities = getActivities(
                $topic['activity']
            );

            foreach ($activities as &$activity) {

                $activity .=
                    ' Focus on building something practical that can be added to your portfolio.';
            }

            unset($activity);

            $topic['activity'] = $activities;
        }

        unset($topic);
    }

if ($goal === 'Prepare for a Job') {

        foreach ($topics as &$topic) {

            if (
                $topic['category'] === 'PROJECT'
                || $topic['category'] === 'CAREER'
            ) {

                $activities = getActivities(
                    $topic['activity']
                );

                foreach ($activities as &$activity) {

                    $activity .=
                        ' Make sure the result is portfolio-ready and can be discussed during an interview.';
                }
                        
                unset($activity);

                $topic['activity'] = $activities;
            }
        }

        unset($topic);
    }

if ($goal === 'Learn the Fundamentals') {

        foreach ($topics as &$topic) {

            if (
                $topic['category'] === 'FOUNDATION'
                || $topic['category'] === 'PROGRAMMING'
            ) {

                $activities = getActivities(
                    $topic['activity']
                );

                foreach ($activities as &$activity) {

                    $activity .=
                        ' Take your time with this activity and make sure you understand the concepts before moving forward.';
                }

                unset($activity);

                $topic['activity'] = $activities;
            }
        }

        unset($topic);
    }

return $topics;
}

// Replaces the user roadmap with generated stages.
function save_generated_topics($pdo, $userId, $user)
{
    
    $aiResult = ai_generate_roadmap($user); 

if ($aiResult['success']) {
        $topics = $aiResult['stages'];
        $source = 'gemini';
        $summary = $aiResult['summary'] ?? '';
    } else {
        $topics = generate_study_topics($user);
        $source = 'fallback';
        $summary = 'A rule-based roadmap was used because Gemini was not available.';
    }

$pdo->beginTransaction();

    try {

$assessmentStmt = $pdo->prepare(
            'SELECT id FROM assessments WHERE user_id = ? LIMIT 1'
        );
        $assessmentStmt->execute([$userId]);
        $assessment = $assessmentStmt->fetch(PDO::FETCH_ASSOC);

if (!$assessment) {
            throw new RuntimeException('Assessment not found.');
        }

$deletePath = $pdo->prepare(
            'DELETE FROM study_paths WHERE user_id = ?'
        );
        $deletePath->execute([$userId]);

$pathStmt = $pdo->prepare(
            'INSERT INTO study_paths
             (user_id, assessment_id, title, description, roadmap_source)
             VALUES (?, ?, ?, ?, ?)'
        );

        $pathStmt->execute([
            $userId,
            $assessment['id'],
            clean_text($user['career_goal'] ?? 'Personalized Study Path', 180),
            clean_text($summary, 2000),
            $source
        ]);

$studyPathId = (int)$pdo->lastInsertId();

$insert = $pdo->prepare(
            'INSERT INTO study_topics
            (study_path_id, order_number, category, title, description, topics,
             activity, activity_progress, expected_output, resources,
             ai_why, roadmap_source, is_read, completed)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

$startingLevel = strtolower(trim((string)($user['current_level'] ?? 'complete beginner')));
        $milestoneSets = [
            'complete beginner' => ['FOUNDATION', 'BEGINNER', 'INTERMEDIATE', 'ADVANCED', 'PROFESSIONAL'],
            'beginner' => ['BEGINNER', 'INTERMEDIATE', 'ADVANCED', 'PROFESSIONAL', 'SPECIALIST'],
            'intermediate' => ['INTERMEDIATE', 'ADVANCED', 'PROFESSIONAL', 'SPECIALIST', 'MASTERY'],
            'advanced' => ['ADVANCED', 'PROFESSIONAL', 'SPECIALIST', 'MASTERY', 'EXPERT']
        ];
        
        $milestones = $milestoneSets[$startingLevel] ?? $milestoneSets['complete beginner'];

foreach ($topics as $index => $topic) {
            $activities = array_values(array_slice(getActivities($topic['activity'] ?? []), 0, 6));
            $milestone = $milestones[min($index, count($milestones) - 1)];

while (count($activities) < 6) {
                $activities[] = 'Review the concepts from this stage and practice them in a small task.';
            }

$insert->execute([
                $studyPathId,
                $index + 1,
                $milestone,
                clean_text($topic['title'] ?? 'Learning Stage', 180),
                clean_text($topic['description'] ?? '', 1000),
                clean_text($topic['topics'] ?? '', 1000),
                json_encode($activities, JSON_UNESCAPED_UNICODE),
                json_encode(array_fill(0, 6, 0), JSON_UNESCAPED_UNICODE),
                clean_text($topic['expected_output'] ?? '', 1000),
                clean_text($topic['resources'] ?? '', 1000),
                clean_text($topic['ai_why'] ?? '', 1000),
                $source,
                0,
                0
            ]);
        }

$pdo->commit();

        return [
            'source' => $source,
            'summary' => $summary,
            'fallback_reason' => $source === 'fallback' ? ($aiResult['message'] ?? 'Gemini unavailable.') : null
        ];
    } catch (Throwable $e) {
        
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        throw $e;
    }
}

// Fetches roadmap stages in display order.
function getStudyTopics($pdo, $userId)
{
    
    $stmt = $pdo->prepare(
        'SELECT st.*
         FROM study_topics st
         INNER JOIN study_paths sp ON sp.id = st.study_path_id
         WHERE sp.user_id = ?
         ORDER BY st.order_number ASC, st.id ASC'
    );

$stmt->execute([$userId]);

return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
