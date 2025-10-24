<?php

define('GEMINI_API_KEY', 'YOUR_GEMINI_API_KEY_HERE');
define('GEMINI_API_URL', 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-image-preview:generateContent');

function generateImage($prompt, $filename, $aspectRatio = '16:9') {
    $payload = [
        'contents' => [
            [
                'role' => 'user',
                'parts' => [
                    ['text' => $prompt]
                ]
            ]
        ],
        'generationConfig' => [
            'temperature' => 0.6,
            'imageConfig' => [
                'aspectRatio' => $aspectRatio
            ]
        ],
        'systemInstruction' => [
            'parts' => [
                ['text' => 'You are a world-class meme designer and social media expert. Create funny, relatable, and visually striking images for tech-savvy millennials and Gen Z. Use bold text, modern design, and humor. Make it shareable.']
            ]
        ]
    ];

    $ch = curl_init(GEMINI_API_URL . '?key=' . GEMINI_API_KEY);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json'
        ]
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        echo "Error generating image: HTTP $httpCode\n";
        echo "Response: $response\n";
        return false;
    }

    $data = json_decode($response, true);

    if (!isset($data['candidates'][0]['content']['parts'][0]['inlineData']['data'])) {
        echo "Error: No image data in response\n";
        print_r($data);
        return false;
    }

    $base64Image = $data['candidates'][0]['content']['parts'][0]['inlineData']['data'];
    $imageData = base64_decode($base64Image);

    file_put_contents($filename, $imageData);
    echo "✓ Generated: $filename\n";

    return true;
}

// Create output directory
if (!is_dir('ads')) {
    mkdir('ads', 0777, true);
}

echo "🎨 Generating X Ad Images with Gemini...\n\n";

// Ad 1: "Gave Up" - Two-panel comparison
$prompt1 = "Create a funny two-panel meme image for a Twitter ad about browser bookmarks.

LEFT PANEL:
- Show a completely empty, minimal browser bookmark bar (just blank space)
- Above it, bold text: 'Me in 2019'
- Clean, organized, zen aesthetic

RIGHT PANEL:
- Show a cluttered, chaotic browser bookmark bar with 50+ tiny overlapping bookmark icons, completely unreadable and messy
- Above it, bold text: 'Me now'
- Overwhelming, stressful look

BOTTOM BANNER:
- Black background with white text: 'Sorted AI: AI fixes both'

Style: Clean, modern UI design, realistic browser chrome aesthetic, relatable tech humor. High contrast, readable text. Make it look like actual Chrome browser screenshots.";

generateImage($prompt1, 'ads/ad1_gave_up.jpg', '16:9');

echo "\n";

// Ad 2: "Screen Share Panic" - Sweating guy meme style
$prompt2 = "Create a meme-style image for a Twitter ad about bookmark privacy panic.

Show a stressed office worker in a video call, sweating nervously, with visible anxiety on their face. They're looking at their screen with panic.

On their computer screen visible in the background, show a browser bookmark bar with embarrassing bookmarks like:
- 'LinkedIn Jobs' (multiple)
- 'How to Quit Your Job'
- 'Indeed.com'
- 'Glassdoor - Company Reviews'

TOP TEXT (bold, meme-style font): 'Boss: Can you screen share?'

BOTTOM TEXT (bold, meme-style font): 'Me with my bookmark bar visible:'

Style: Relatable office meme aesthetic, modern and clean, humor-focused. Person should look like a typical millennial/gen-z office worker. High quality, shareable meme format.";

generateImage($prompt2, 'ads/ad2_screen_share_panic.jpg', '1:1');

echo "\n";

// Ad 3: "Expectation vs Reality" - Split screen
$prompt3 = "Create a funny expectation vs reality split-screen image for a Twitter ad about bookmark organization.

LEFT SIDE - 'EXPECTATION':
- Perfectly organized browser bookmarks in neat folders:
  - 📁 Work (perfectly aligned)
  - 📁 Learning (tidy)
  - 📁 Side Projects (organized)
  - 📁 Personal (clean)
- Minimalist, zen, Marie Kondo aesthetic
- Soft, calming colors
- Label at top: 'EXPECTATION'

RIGHT SIDE - 'REALITY':
- Complete chaos: one giant folder called 'Stuff' with 500+ bookmarks
- Random unsorted links everywhere
- No organization whatsoever
- Stress-inducing, overwhelming
- Label at top: 'REALITY'

BOTTOM BANNER:
- 'Let AI organize your chaos in 60 seconds - Sorted AI'

Style: Modern, clean UI design, relatable tech humor, high contrast between both sides. Make it look like actual browser screenshots.";

generateImage($prompt3, 'ads/ad3_expectation_reality.jpg', '16:9');

echo "\n";

// Ad 4: "Bookmark Hoarder" - Overwhelming visual
$prompt4 = "Create a humorous, overwhelming image for a Twitter ad about bookmark hoarding.

Show a person sitting at their desk, drowning in a massive pile of physical books, papers, and bookmarks (actual paper bookmarks) that are overflowing everywhere, burying them.

Above them, a speech bubble: 'I have a system, I swear!'

On their computer screen in the background, show a browser with 487 tabs open and an impossibly crowded bookmark bar.

At the bottom, a clean white banner with text: 'Stop lying to yourself. Sorted AI - AI organizes your digital mess'

Style: Slightly exaggerated, humorous editorial-style photo illustration. Person should look overwhelmed but relatable. Modern office setting. High quality, shareable format.";

generateImage($prompt4, 'ads/ad4_hoarder.jpg', '4:3');

echo "\n";

// Ad 5: "The Bookmarks Expose You" - Privacy scare
$prompt5 = "Create a darkly humorous image for a Twitter ad about what your bookmarks reveal about you.

Center: A browser bookmark bar displayed like evidence on a detective's board, with red strings connecting different bookmarks.

Bookmarks visible (written clearly):
- '47 productivity apps' → (red line) → 'Opened: 0'
- 'Python tutorial x12' → (red line) → 'Still can\'t code'
- 'LinkedIn Jobs' → (red line) → 'Currently employed'
- 'Best resignation letters' → (red line) → 'Boss doesn\'t know'

Detective's hand pointing at the evidence with a magnifying glass.

TOP TEXT: 'Your bookmarks tell a story'

BOTTOM TEXT: 'Let AI organize your secrets - Sorted AI'

Style: Detective noir aesthetic meets modern tech humor. Dark background, dramatic lighting, conspiracy board vibes. Make it funny but professional.";

generateImage($prompt5, 'ads/ad5_exposed.jpg', '1:1');

echo "\n";

// Ad 6: "Drake Meme" - Classic format
$prompt6 = "Create a Drake meme format image for a Twitter ad about bookmark organization.

TOP PANEL (Drake disapproving, hand up, turning away):
- Drake looking disappointed and rejecting
- Text on right side: 'Organizing 500 bookmarks yourself'
- Clean, bright background

BOTTOM PANEL (Drake approving, pointing and smiling):
- Drake looking happy and pointing in approval
- Text on right side: 'Letting AI do it in 60 seconds for $5'
- Clean, bright background

Use the classic Drake meme format, professional photography style. Drake should be wearing a casual stylish outfit. High quality, instantly recognizable meme format.

Small text at bottom: 'Sorted AI'

Style: Classic Drake meme aesthetic, clean and professional, instantly shareable. Make it look like the authentic meme template.";

generateImage($prompt6, 'ads/ad6_drake_meme.jpg', '1:1');

echo "\n✨ All images generated!\n\n";
echo "Check the 'ads' folder for your X ad images.\n";
