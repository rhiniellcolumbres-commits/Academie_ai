<?php

require_once __DIR__ . '/../config/gemini.php';

if (!defined('ROADMAP_ACTIVITIES_PER_STAGE')) {
    define('ROADMAP_ACTIVITIES_PER_STAGE', 8);
}

// Trims a list of scalar values and removes blank entries.
function ai_clean_string_list($items)
{
    $cleaned = [];

    foreach ((array)$items as $item) {
        if (!is_scalar($item)) {
            continue;
        }

        $item = trim((string)$item);
        if ($item !== '' && $item !== '0') {
            $cleaned[] = $item;
        }
    }

    return $cleaned;
}

// Defines the expected structure of an AI generated roadmap.
function ai_roadmap_schema()
{
    
    return [
        'type' => 'object',
        'properties' => [
            'summary' => [
                'type' => 'string'
            ],
            'stages' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'category' => [
                            'type' => 'string'
                        ],
                        'title' => [
                            'type' => 'string'
                        ],
                        'description' => [
                            'type' => 'string'
                        ],
                        'topics' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'string'
                            ]
                        ],
                        'activities' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'string'
                            ]
                        ],
                        'expected_output' => [
                            'type' => 'string'
                        ],
                        'resources' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'string'
                            ]
                        ],
                        'why_this_stage' => [
                            'type' => 'string'
                        ]
                    ],
                    'required' => [
                        'category',
                        'title',
                        'description',
                        'topics',
                        'activities',
                        'expected_output',
                        'resources',
                        'why_this_stage'
                    ]
                ]
            ]
        ],
        'required' => [
            'summary',
            'stages'
        ]
    ];
}

// Builds the prompt used to generate a personalized roadmap.
function ai_build_roadmap_prompt($user)
{
    
    $level = trim((string)($user['current_level'] ?? 'Beginner'));
    $goal = trim((string)($user['improvement_goal'] ?? 'Improve My Skills'));
    $career = trim((string)($user['career_goal'] ?? ''));
    $interests = trim((string)($user['interests'] ?? ''));
    $skills = trim((string)($user['skills'] ?? ''));

return "You are Academie, an academic study-roadmap planner for college students.

Create a practical and personalized learning roadmap for this student.

The roadmap must match the student's current level, interests, existing skills, improvement goal, and recommended technology career path. Do not require or assume a college course or degree.

The student may belong to ANY academic discipline. Do not assume the student is an IT student.

Student profile:

Current level: {$level}
Improvement goal: {$goal}
Career goal: {$career}
Interests: {$interests}
Existing skills: {$skills}

Rules:

- Generate exactly 6 ordered stages that form a clear progression toward professional readiness.
- Start at the student's self-reported current level; do not force an advanced learner to repeat beginner material.
- For a complete beginner, cover foundations first; then progress through beginner practice, intermediate projects, advanced real-world work, and a professional-ready capstone.
- Use each stage as a milestone with a clear level label in category (for example FOUNDATION, BEGINNER, INTERMEDIATE, ADVANCED, PROFESSIONAL).
- Each stage must have a specific achievement title and a measurable expected_output that proves what the student can do after finishing it.
- Later stages must build on skills from earlier stages, becoming progressively more challenging.
- Avoid assuming the student is studying a particular college course or degree.
- Avoid repeating skills the student already has unless they are important prerequisites.
- Every stage must contain 5 to 7 concise topics.
- Every stage must contain exactly 8 practical activities, ordered from a small first step to a slightly more involved application.
- Keep each activity focused and achievable in one short learning session; split broad tasks into smaller steps instead of combining multiple skills.
- Include a mix of guided practice, independent practice, and a small application or reflection. Avoid filler and repeated activities.
- Activities must be realistic things a college student can practice, complete, or produce.
- expected_output must describe a concrete result the student can demonstrate.
- Give 2 to 4 useful resource names per stage.
- Do not invent URLs.
- why_this_stage must explain why the stage is appropriate for this particular student.
- Keep the roadmap realistic and achievable.
- Do not make the roadmap unnecessarily difficult.
- Do not include country-specific professional licensing requirements unless they are clearly relevant to the student's provided information.
- Return only the requested structured JSON data.";
}

// Sends a prompt to Gemini and returns its response.
function gemini_interaction_request($prompt, $schema = null)
{
    
    if (!gemini_is_configured()) {
        return [
            'success' => false,
            'configured' => false,
            'message' => 'Gemini API key is not configured.'
        ];
    }

if (!function_exists('curl_init')) {
        return [
            'success' => false,
            'configured' => true,
            'message' => 'PHP cURL is not enabled.'
        ];
    }

$url = 'https://generativelanguage.googleapis.com/v1beta/interactions';

    $payload = [
        'model' => 'gemini-3.5-flash-lite',
        'input' => $prompt,
        'generation_config' => [
            'thinking_level' => 'minimal'
        ]
    ];

if ($schema !== null) {
        $payload['response_format'] = [
            'type' => 'text',
            'mime_type' => 'application/json',
            'schema' => $schema
        ];
    }

$maxAttempts = 2;
    $lastMessage = 'Gemini API request failed.';
    $lastHttpCode = 0;

    for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {

$ch = curl_init($url);

curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'x-goog-api-key: ' . GEMINI_API_KEY
            ],
            CURLOPT_POSTFIELDS => json_encode(
                $payload,
                JSON_UNESCAPED_UNICODE
            )
        ]);

$raw = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);

curl_close($ch);

if ($raw === false || $curlError !== '') {
            $lastMessage = 'Gemini request failed: ' . $curlError;
            $lastHttpCode = $httpCode;
        } else {

$response = json_decode($raw, true);
                
            if ($httpCode >= 200 && $httpCode < 300) {

$text = '';

if (!empty($response['candidates'][0]['content']['parts'])) {
    
    foreach ($response['candidates'][0]['content']['parts'] as $part) {
        if (isset($part['text']) && is_string($part['text'])) {
            $text .= $part['text'];
        }
    }
}

if ($text === '' && !empty($response['steps'])) {
    
    foreach (array_reverse($response['steps']) as $step) {
        if (
            ($step['type'] ?? '') === 'model_output' &&
            !empty($step['content'])
        ) {
            foreach ($step['content'] as $content) {
                if (($content['type'] ?? '') === 'text') {
                    $text = $content['text'] ?? '';
                    break 2;
                }
            }
        }
    }
}

                if ($text === '') {
                    
                    return [
                        'success' => false,
                        'configured' => true,
                        'message' => 'Gemini returned an empty response.',
                        'http_code' => $httpCode
                    ];
                }

return [
                    'success' => true,
                    'configured' => true,
                    'text' => $text
                ];
            }

$lastMessage =
                $response['error']['message']
                ?? 'Gemini API returned an error.';

$lastHttpCode = $httpCode;

            if (
                in_array(
                    $httpCode,
                    [429, 500, 502, 503, 504],
                    true
                )
            ) {

                if ($attempt < $maxAttempts) {

sleep($attempt * 2);

                    continue;
                }
            }

            break;
        }

        if ($attempt < $maxAttempts) {
            
            sleep($attempt * 2);
        }
    }

return [
        'success' => false,
        'configured' => true,
        'message' => $lastMessage,
        'http_code' => $lastHttpCode
    ];
}

// Generates and validates a personalized roadmap.
function ai_generate_roadmap($user)
{
    
    $result = gemini_interaction_request(
    ai_build_roadmap_prompt($user),
    ai_roadmap_schema()
);

if (!$result['success']) {
        return $result;
    }

$data = json_decode($result['text'], true);

if (
        !is_array($data) ||
        !isset($data['stages']) ||
        !is_array($data['stages'])
    ) {
        return [
            'success' => false,
            'configured' => true,
            'message' => 'Gemini returned an invalid roadmap format.'
        ];
    }

$stages = [];

foreach (array_slice($data['stages'], 0, 6) as $stage) {

        $activities = ai_clean_string_list($stage['activities'] ?? []);
        $topics = ai_clean_string_list($stage['topics'] ?? []);
        $resources = ai_clean_string_list($stage['resources'] ?? []);

$title = trim(
            (string)($stage['title'] ?? '')
        );

        if (
            $title === '' ||
            count($activities) < 3 ||
            count($topics) < 1
        ) {
            continue;
        }

$activities = array_slice($activities, 0, ROADMAP_ACTIVITIES_PER_STAGE);

        while (count($activities) < ROADMAP_ACTIVITIES_PER_STAGE) {
            $step = count($activities) + 1;
            $activities[] = $step === 7
                ? 'Explain one key idea from this stage in your own words and give a simple example.'
                : 'Review your work from this stage, correct one mistake, and note what you learned.';
        }

$stages[] = [
            'category' => strtoupper(
                substr(
                    trim((string)($stage['category'] ?? '')),
                    0,
                    100
                )   
            ),

            'title' => $title,

            'description' => trim(
                (string)($stage['description'] ?? '')
            ),

            'topics' => implode(
                ', ',
                array_slice($topics, 0, 5)
            ),

            'activity' => $activities,

            'expected_output' => trim(
                (string)($stage['expected_output'] ?? '')
            ),

            'resources' => implode(
                ', ',
                array_slice($resources, 0, 4)
            ),

            'ai_why' => trim(
                (string)($stage['why_this_stage'] ?? '')
            )
        ];
    }

if (count($stages) < 5) {
        return [
            'success' => false,
            'configured' => true,
            'message' => 'Gemini returned too few usable stages.'
        ];
    }

return [
        'success' => true,
        'configured' => true,
        'summary' => trim(
            (string)($data['summary'] ?? '')
        ),
        'stages' => $stages
    ];
}

// Generates a lesson for a roadmap activity.
function ai_activity_lesson($user, $topic, $activity)
{
    
    $schema = [
        'type' => 'object',
        'properties' => [
            'lesson' => ['type' => 'string'],
            'example' => ['type' => 'string'],
            'practice' => ['type' => 'string'],
            'checklist' => [
                'type' => 'array',
                'items' => ['type' => 'string']
            ],
            'resources' => [
                'type' => 'array',
                'items' => ['type' => 'string']
            ]
        ],
        'required' => ['lesson', 'example', 'practice', 'checklist', 'resources']
    ];

$level = trim((string)($user['current_level'] ?? 'Beginner'));
    $career = trim((string)($user['career_goal'] ?? ''));
    $goal = trim((string)($user['improvement_goal'] ?? ''));
    $interests = trim((string)($user['interests'] ?? ''));
    $skills = trim((string)($user['skills'] ?? ''));
    $stageTitle = trim((string)($topic['title'] ?? ''));
    $stageTopics = trim((string)($topic['topics'] ?? ''));
    $stageDescription = trim((string)($topic['description'] ?? ''));

$prompt = "You are Academie, an AI tutor that creates useful, accurate, topic-specific learning guides for students from ANY academic discipline.\n\n"
        . "Create a complete lesson that teaches the learner the knowledge needed for this exact activity. Do not merely rephrase the activity. Never give generic filler such as 'break the task into steps' without teaching the actual subject.\n\n"
        . "LEARNER PROFILE\n"
        . "Current level: {$level}\n"
        . "Career or learning goal: {$career}\n"
        . "Improvement goal: {$goal}\n"
        . "Interests: {$interests}\n"
        . "Existing skills: {$skills}\n\n"
        . "LEARNING CONTEXT\n"
        . "Stage: {$stageTitle}\n"
        . "Stage description: {$stageDescription}\n"
        . "Related topics: {$stageTopics}\n"
        . "Exact activity: {$activity}\n\n"
        . "Return these five sections as JSON fields:\n\n"
        . "1. lesson: Write approximately 120-180 words in plain text, split into readable paragraphs. Start with a clear definition or introduction. Explain the main ideas and important terms, why the concept matters, and the key steps or reasoning the learner should follow. Include one useful tip or common mistake. Match the student's level and teach the actual topic. Do not include the Example or Practice section here.\n\n"
        . "2. example: Give one concise, specific worked example related to the activity. For programming or technical topics, include a small correct code example and state its expected result. For other subjects, give a realistic worked example.\n\n"
        . "3. practice: Give one focused hands-on task with clear requirements and a short description of what the result should demonstrate.\n\n"
        . "4. checklist: Provide exactly 3 specific self-check questions or statements about this activity. Avoid generic checks.\n\n"
        . "5. resources: Provide 2 real, relevant resource names, preferring official documentation, educational sites, or well-known textbooks. Do not invent URLs.\n\n"
        . "Quality rules: The lesson must be specific to the exact activity and stage; explain rather than summarize; use beginner-friendly language without talking down to the learner; do not assume the learner is an IT student; do not invent facts, citations, or URLs; do not include HTML; return only valid JSON matching the schema.";

    $result = gemini_interaction_request($prompt, $schema);

if (empty($result['success'])) {
        return [
            'success' => false,
            'configured' => $result['configured'] ?? true,
            'message' => $result['message'] ?? 'The AI lesson could not be generated.',
            'http_code' => $result['http_code'] ?? 0
        ];
    }

    $data = json_decode((string)($result['text'] ?? ''), true);

    if (!is_array($data)) {
        return [
            'success' => false,
            'configured' => true,
            'message' => 'The AI returned an invalid lesson format. Please try again.'
        ];
    }

    $lesson = trim((string)($data['lesson'] ?? ''));
    $example = trim((string)($data['example'] ?? ''));
    $practice = trim((string)($data['practice'] ?? ''));
    
    $checklist = ai_clean_string_list($data['checklist'] ?? []);
    $resources = ai_clean_string_list($data['resources'] ?? []);

if ((function_exists('mb_strlen') ? mb_strlen($lesson) : strlen($lesson)) < 400
        || (function_exists('mb_strlen') ? mb_strlen($example) : strlen($example)) < 40
        || (function_exists('mb_strlen') ? mb_strlen($practice) : strlen($practice)) < 40
        || count($checklist) < 3 || count($resources) < 2) {
        return [
            'success' => false,
            'configured' => true,
            'message' => 'The AI returned an incomplete lesson. Please try generating it again.'
        ];
    }

    return [
        'success' => true,
        'source' => 'gemini',
        'lesson' => $lesson,
        'example' => $example,
        'practice' => $practice,
        'checklist' => array_slice($checklist, 0, 6),
        'resources' => array_slice($resources, 0, 4)
    ];
}
