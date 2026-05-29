<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\AdPlacement;
use App\Models\AffiliateLink;
use App\Models\AiTool;
use App\Models\AiSkillGuide;
use App\Models\Article;
use App\Models\AuthorProfile;
use App\Models\Category;
use App\Models\AiComparison;
use App\Models\DailyAiBrief;
use App\Models\LearningLesson;
use App\Models\LearningPath;
use App\Models\PromptTemplate;
use App\Models\SeoMeta;
use App\Models\Tag;
use App\Models\User;
use App\Models\WebStory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PlatformSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::updateOrCreate(
            ['email' => 'admin@aihindinews.com'],
            [
                'name' => 'Chief Editor',
                'password' => Hash::make('password'),
                'role' => UserRole::SuperAdmin->value,
                'is_verified_author' => true,
                'designation' => 'Editor-in-Chief',
            ]
        );

        AuthorProfile::updateOrCreate(
            ['user_id' => $admin->id],
            [
                'display_name' => 'Chief Editor',
                'slug' => 'chief-editor',
                'bio' => 'Leading AI-assisted Hindi journalism with human editorial oversight.',
                'is_featured' => true,
            ]
        );

        $categories = [
            ['name' => 'राष्ट्र', 'slug' => 'rashtr', 'locale' => 'hi'],
            ['name' => 'तकनीक', 'slug' => 'tech', 'locale' => 'hi'],
            ['name' => 'वित्त', 'slug' => 'finance', 'locale' => 'hi'],
            ['name' => 'AI Tools', 'slug' => 'ai-tools', 'locale' => 'en'],
        ];

        foreach ($categories as $i => $cat) {
            Category::updateOrCreate(['slug' => $cat['slug']], [...$cat, 'sort_order' => $i, 'is_active' => true]);
        }

        $tech = Category::where('slug', 'tech')->first();

        $articles = [
            [
                'title' => 'भारत में AI समाचार क्रांति: नया मीडिया युग शुरू',
                'slug' => 'bharat-ai-news-revolution',
                'locale' => 'hi',
                'content_type' => 'explainer',
                'excerpt' => 'AI और मानव संपादन के मेल से भारतीय समाचार उद्योग तेजी से बदल रहा है।',
                'ai_summary' => 'भारत में AI-सहायता प्राप्त हिंदी समाचार प्लेटफॉर्म तेजी से बढ़ रहे हैं, जहां मशीन गति और मानव विश्वसनीयता का संयोजन पाठकों को बेहतर अनुभव देता है।',
                'key_points' => ['AI + मानव समीक्षा', 'Google Discover अनुकूलन', 'मोबाइल-प्रथम पाठन अनुभव'],
                'body' => '<p>भारत की डिजिटल मीडिया दुनिया में AI-native समाचार प्लेटफॉर्म एक नई लहर लेकर आ रहे हैं।</p><p>ये प्लेटफॉर्म केवल स्वचालित सामग्री नहीं बनाते — संपादकीय टीम तथ्यों की पुष्टि करती है और विश्लेषण जोड़ती है।</p>',
                'is_featured' => true,
                'is_breaking' => true,
                'human_reviewed' => true,
                'fact_checked' => true,
            ],
            [
                'title' => 'Best AI Tools for Hindi Content Creators in 2026',
                'slug' => 'best-ai-tools-hindi-creators-2026',
                'locale' => 'en',
                'content_type' => 'review',
                'excerpt' => 'A curated list of AI tools that help Hindi creators write, translate, and publish faster.',
                'ai_summary' => 'Hindi creators can boost productivity with AI writing, translation, and SEO tools vetted for quality and EEAT compliance.',
                'key_points' => ['Writing assistants', 'Translation APIs', 'SEO automation'],
                'body' => '<p>Content creators need tools that respect language nuance and editorial quality.</p>',
                'is_featured' => true,
                'human_reviewed' => true,
            ],
        ];

        foreach ($articles as $data) {
            $article = Article::updateOrCreate(
                ['slug' => $data['slug']],
                [
                    ...$data,
                    'author_id' => $admin->id,
                    'category_id' => $tech?->id,
                    'status' => 'published',
                    'published_at' => now()->subHours(rand(1, 48)),
                    'reading_time_minutes' => 4,
                    'views_count' => rand(500, 5000),
                    'india_impact_score' => $data['locale'] === 'hi' ? 88 : 76,
                    'india_impact_summary' => 'Indian readers ke liye yeh update Hindi content, productivity, aur digital skills adoption ko directly affect karta hai.',
                    'ai_opportunity_score' => $data['content_type'] === 'review' ? 91 : 82,
                    'ai_opportunity_summary' => 'Is topic se creators, students, job seekers, aur small businesses practical AI workflows build kar sakte hain.',
                    'audience_roles' => $data['content_type'] === 'review' ? ['creator', 'student', 'business'] : ['creator', 'student'],
                ]
            );

            SeoMeta::updateOrCreate(
                ['seoable_type' => Article::class, 'seoable_id' => $article->id],
                [
                    'meta_title' => $article->title,
                    'meta_description' => $article->excerpt,
                    'discover_optimized' => true,
                ]
            );
        }

        Tag::updateOrCreate(['slug' => 'ai-news'], ['name' => 'AI News', 'locale' => 'en']);
        Tag::updateOrCreate(['slug' => 'hindi-media'], ['name' => 'हिंदी मीडिया', 'locale' => 'hi']);

        AdPlacement::updateOrCreate(
            ['slot_key' => 'article_top'],
            ['name' => 'Article Top', 'page_type' => 'article', 'ad_format' => 'display', 'reserved_width' => 970, 'reserved_height' => 90, 'revenue_channel' => 'adsense', 'ad_code' => '<div class="text-center text-sm text-zinc-400">Sponsored story placement</div>', 'lazy_load' => true, 'is_active' => true, 'priority' => 10]
        );

        AdPlacement::updateOrCreate(
            ['slot_key' => 'article_inline_1'],
            ['name' => 'Article Inline 1', 'page_type' => 'article', 'ad_format' => 'display', 'reserved_width' => 728, 'reserved_height' => 180, 'revenue_channel' => 'adsense', 'ad_code' => '<div class="text-center text-sm text-zinc-400">In-article sponsored placement</div>', 'lazy_load' => true, 'is_active' => true, 'priority' => 8]
        );

        AdPlacement::updateOrCreate(
            ['slot_key' => 'article_inline_2'],
            ['name' => 'Article Inline 2', 'page_type' => 'article', 'ad_format' => 'display', 'reserved_width' => 728, 'reserved_height' => 180, 'revenue_channel' => 'adsense', 'ad_code' => '<div class="text-center text-sm text-zinc-400">Mid-article ad space</div>', 'lazy_load' => true, 'is_active' => true, 'priority' => 7]
        );

        AdPlacement::updateOrCreate(
            ['slot_key' => 'article_bottom'],
            ['name' => 'Article Bottom', 'page_type' => 'article', 'ad_format' => 'display', 'reserved_width' => 970, 'reserved_height' => 120, 'revenue_channel' => 'adsense', 'ad_code' => '<div class="text-center text-sm text-zinc-400">Sponsored recommendation at the end of the article</div>', 'lazy_load' => true, 'is_active' => true, 'priority' => 5]
        );

        AdPlacement::updateOrCreate(
            ['slot_key' => 'article_sidebar'],
            ['name' => 'Article Sidebar', 'page_type' => 'article', 'ad_format' => 'display', 'reserved_width' => 300, 'reserved_height' => 250, 'revenue_channel' => 'adsense', 'ad_code' => '<div class="text-center text-sm text-zinc-400">Sidebar brand placement</div>', 'lazy_load' => true, 'is_active' => true, 'priority' => 6]
        );

        AdPlacement::updateOrCreate(
            ['slot_key' => 'utility_top'],
            ['name' => 'Utility Top', 'page_type' => 'utility', 'ad_format' => 'display', 'reserved_width' => 970, 'reserved_height' => 120, 'revenue_channel' => 'adsense', 'ad_code' => '<div class="text-center text-sm text-zinc-400">Helpful tools sponsor placement</div>', 'lazy_load' => true, 'is_active' => true, 'priority' => 9]
        );

        AdPlacement::updateOrCreate(
            ['slot_key' => 'utility_results'],
            ['name' => 'Utility Results', 'page_type' => 'utility', 'ad_format' => 'display', 'reserved_width' => 728, 'reserved_height' => 180, 'revenue_channel' => 'adsense', 'ad_code' => '<div class="text-center text-sm text-zinc-400">Relevant creator tools sponsor</div>', 'lazy_load' => true, 'is_active' => true, 'priority' => 7]
        );

        AdPlacement::updateOrCreate(
            ['slot_key' => 'ai_tools_top'],
            ['name' => 'AI Tools Top', 'page_type' => 'utility', 'ad_format' => 'display', 'reserved_width' => 970, 'reserved_height' => 120, 'revenue_channel' => 'adsense', 'ad_code' => '<div class="text-center text-sm text-zinc-400">AI tools sponsor placement</div>', 'lazy_load' => true, 'is_active' => true, 'priority' => 8]
        );

        AdPlacement::updateOrCreate(
            ['slot_key' => 'prompts_top'],
            ['name' => 'Prompts Top', 'page_type' => 'utility', 'ad_format' => 'display', 'reserved_width' => 970, 'reserved_height' => 120, 'revenue_channel' => 'adsense', 'ad_code' => '<div class="text-center text-sm text-zinc-400">Prompt library sponsor placement</div>', 'lazy_load' => true, 'is_active' => true, 'priority' => 8]
        );

        AdPlacement::updateOrCreate(
            ['slot_key' => 'daily_brief_sidebar'],
            ['name' => 'Daily Brief Sidebar', 'page_type' => 'utility', 'ad_format' => 'display', 'reserved_width' => 300, 'reserved_height' => 280, 'revenue_channel' => 'adsense', 'ad_code' => '<div class="text-center text-sm text-zinc-400">Daily brief sponsor placement</div>', 'lazy_load' => true, 'is_active' => true, 'priority' => 6]
        );

        AffiliateLink::updateOrCreate(
            ['slug' => 'chatgpt-plus'],
            [
                'name' => 'ChatGPT Plus',
                'category' => 'ai-tools',
                'description' => 'Premium AI writing assistant',
                'destination_url' => 'https://openai.com/chatgpt',
                'tracking_code' => 'CHATGPT-PLUS',
                'is_active' => true,
            ]
        );

        AiTool::updateOrCreate(
            ['slug' => 'chatgpt'],
            [
                'name' => 'ChatGPT',
                'category' => 'AI Writing',
                'tagline' => 'Students, creators, aur businesses ke liye all-round AI assistant.',
                'description' => 'Hindi prompts, explainers, emails, scripts, coding help, aur research summaries ke liye useful AI tool.',
                'website_url' => 'https://chatgpt.com',
                'pricing' => 'Free + paid',
                'best_for' => ['Students', 'Creators', 'Small business'],
                'pros' => ['Fast writing help', 'Hindi and Hinglish prompts work well', 'Useful for scripts and summaries'],
                'cons' => ['Facts should be verified', 'Best features may need paid plan'],
                'alternatives' => ['Gemini', 'Claude', 'Perplexity'],
                'use_cases' => ['Hindi YouTube scripts', 'Blog outlines and explainers', 'Resume and email writing', 'Prompt-based business workflows'],
                'faqs' => [
                    ['question' => 'ChatGPT Hindi me kaam karta hai?', 'answer' => 'Haan, ChatGPT Hindi aur Hinglish prompts par kaafi useful output de sakta hai. Important facts ko publish karne se pehle verify karein.'],
                    ['question' => 'ChatGPT free hai?', 'answer' => 'ChatGPT ka free plan available hota hai, lekin advanced models/features ke liye paid plan ki zarurat ho sakti hai.'],
                ],
                'seo_title' => 'ChatGPT Review in Hindi: Pricing, Use Cases, Pros, Cons',
                'seo_description' => 'ChatGPT ka Hindi review: best use cases, pricing, pros, cons, alternatives aur FAQs for students, creators and business users.',
                'rating' => 4.8,
                'trust_score' => 88,
                'trust_breakdown' => ['pricing_clarity' => 82, 'privacy_signal' => 78, 'usefulness' => 94, 'alternatives' => 90],
                'opportunity_score' => 94,
                'opportunity_summary' => 'ChatGPT writing, coding, job prep, scripts, and business automation ke liye high-opportunity tool hai.',
                'audience_roles' => ['student', 'creator', 'job_seeker', 'business'],
                'is_featured' => true,
                'is_active' => true,
                'published_at' => now(),
            ]
        );

        AiTool::updateOrCreate(
            ['slug' => 'perplexity'],
            [
                'name' => 'Perplexity',
                'category' => 'AI Search',
                'tagline' => 'Research answers with cited sources.',
                'description' => 'News research, comparisons, and source-backed summaries ke liye strong AI search tool.',
                'website_url' => 'https://www.perplexity.ai',
                'pricing' => 'Free + paid',
                'best_for' => ['Research', 'News tracking', 'Students'],
                'pros' => ['Cited answers', 'Fast research', 'Good for comparison pages'],
                'cons' => ['Sources still need manual checking', 'Some advanced features are paid'],
                'alternatives' => ['ChatGPT Search', 'Gemini', 'Google AI Mode'],
                'use_cases' => ['AI news research', 'Comparison page research', 'Source-backed explainers', 'Student research summaries'],
                'faqs' => [
                    ['question' => 'Perplexity kis kaam ke liye best hai?', 'answer' => 'Perplexity source-backed research, quick explanations, aur comparison research ke liye useful hai.'],
                    ['question' => 'Kya Perplexity ke answers verify karne chahiye?', 'answer' => 'Haan. Citations help karte hain, lekin final publishing se pehle source quality aur facts verify karne chahiye.'],
                ],
                'seo_title' => 'Perplexity AI Review in Hindi: Research Tool, Pricing, Pros, Cons',
                'seo_description' => 'Perplexity AI ka Hindi review: research use cases, citations, pricing, alternatives and FAQs for students and creators.',
                'rating' => 4.7,
                'trust_score' => 86,
                'trust_breakdown' => ['pricing_clarity' => 82, 'privacy_signal' => 84, 'usefulness' => 90, 'alternatives' => 88],
                'opportunity_score' => 88,
                'opportunity_summary' => 'Perplexity students, researchers, writers, and news teams ke liye source-backed research speed improve karta hai.',
                'audience_roles' => ['student', 'creator', 'business'],
                'is_featured' => true,
                'is_active' => true,
                'published_at' => now(),
            ]
        );

        PromptTemplate::updateOrCreate(
            ['slug' => 'youtube-video-script-hindi'],
            [
                'title' => 'YouTube video script in Hindi',
                'category' => 'YouTube',
                'audience' => 'Creators',
                'language' => 'hinglish',
                'use_case' => 'Turn any topic into a retention-friendly Hindi YouTube script.',
                'prompt' => 'Act as a Hindi YouTube scriptwriter. Topic: [TOPIC]. Write a hook, intro, 5 key points, examples, and a strong CTA in Hinglish.',
                'tags' => ['YouTube', 'Hindi', 'Script'],
                'is_featured' => true,
                'is_active' => true,
                'published_at' => now(),
            ]
        );

        PromptTemplate::updateOrCreate(
            ['slug' => 'resume-bullet-improver'],
            [
                'title' => 'Resume bullet improver',
                'category' => 'Jobs',
                'audience' => 'Job seekers',
                'language' => 'english',
                'use_case' => 'Improve resume bullets with action verbs and measurable impact.',
                'prompt' => 'Rewrite these resume bullets with action verbs, numbers, and impact. Keep them ATS friendly: [PASTE BULLETS]',
                'tags' => ['Resume', 'Jobs', 'Career'],
                'is_featured' => true,
                'is_active' => true,
                'published_at' => now(),
            ]
        );

        DailyAiBrief::updateOrCreate(
            ['slug' => 'aaj-ka-ai-brief'],
            [
                'title' => 'Aaj ka AI Brief: 30 second me samjho',
                'summary' => 'AI duniya ke useful updates, India impact, tool of the day, aur copy-ready prompts ek jagah.',
                'key_updates' => [
                    'AI tools ko Hindi creators ke workflow se jodne ka demand fast badh raha hai.',
                    'Prompt libraries repeat users aur long-tail SEO traffic ke liye strong asset ban sakti hain.',
                    'Tool reviews affiliate revenue aur AdSense RPM dono improve kar sakte hain.',
                ],
                'tool_of_day' => ['name' => 'Perplexity', 'url' => '/ai-tools/perplexity', 'reason' => 'Research aur cited answers ke liye beginners ke liye easy entry point.'],
                'prompts' => ['Explain [TOPIC] in simple Hindi with examples.', 'Create 5 Instagram reel hooks for [TOPIC] in Hinglish.'],
                'impact_india' => 'Hindi creators, job seekers, aur small businesses ke liye AI adoption fast ho raha hai. Daily practical guidance repeat traffic ka strong reason ban sakta hai.',
                'voice_script' => 'Namaste. Yeh hai aaj ka AI Hindi News voice brief. AI tools ko Hindi creators ke workflow se jodne ka demand fast badh raha hai. Prompt libraries repeat users aur long-tail SEO traffic ke liye strong asset ban sakti hain. Tool reviews affiliate revenue aur AdSense RPM dono improve kar sakte hain. India impact: Hindi creators, job seekers, aur small businesses ke liye AI adoption fast ho raha hai.',
                'voice_duration_seconds' => 55,
                'cta_label' => 'Explore AI tools',
                'cta_url' => '/ai-tools',
                'status' => 'published',
                'published_at' => now(),
            ]
        );

        AiComparison::updateOrCreate(
            ['slug' => 'chatgpt-vs-gemini-hindi-users'],
            [
                'title' => 'ChatGPT vs Gemini: Hindi users ke liye kaunsa better hai?',
                'category' => 'AI Assistants',
                'tool_a' => 'ChatGPT',
                'tool_b' => 'Gemini',
                'summary' => 'ChatGPT writing, prompts aur structured workflows me strong hai. Gemini Google ecosystem, multimodal tasks aur Android users ke liye useful hai.',
                'winner' => 'ChatGPT for writing, Gemini for Google ecosystem',
                'best_for' => ['Students who need writing help', 'Creators making Hindi/Hinglish content', 'Users comparing free AI assistants'],
                'scorecard' => [
                    ['label' => 'Hindi writing', 'a' => 'Very strong', 'b' => 'Good'],
                    ['label' => 'Research', 'a' => 'Good with browsing/search setup', 'b' => 'Good in Google ecosystem'],
                    ['label' => 'Creative scripts', 'a' => 'Very strong', 'b' => 'Good'],
                    ['label' => 'Free usage', 'a' => 'Good', 'b' => 'Good'],
                ],
                'pros_cons' => [
                    'Choose ChatGPT when your main work is writing, ideation, scripts, emails, or prompt workflows.',
                    'Choose Gemini when you prefer Google products, Android workflows, or multimodal assistance.',
                    'For important facts, verify outputs from both tools with reliable sources.',
                ],
                'faqs' => [
                    ['question' => 'Hindi content ke liye kaunsa better hai?', 'answer' => 'Writing and prompt control ke liye ChatGPT usually stronger feel hota hai, while Gemini Google ecosystem users ke liye convenient hai.'],
                    ['question' => 'Free users ke liye kaunsa tool choose karein?', 'answer' => 'Dono try karein. Agar writing ka kaam zyada hai to ChatGPT start karein; research/Google workflow ke liye Gemini useful hai.'],
                ],
                'cta_label' => 'Explore AI tools',
                'cta_url' => '/ai-tools',
                'is_featured' => true,
                'status' => 'published',
                'published_at' => now(),
            ]
        );

        $promptPath = LearningPath::updateOrCreate(
            ['slug' => 'prompt-engineering-hindi-creators'],
            [
                'title' => 'Prompt Engineering for Hindi Creators',
                'category' => 'Prompt Engineering',
                'level' => 'beginner',
                'description' => 'Hindi/Hinglish prompts likhna seekhein jo YouTube, blogs, social media aur business workflows me kaam aayein.',
                'outcomes' => ['Write clearer prompts', 'Create reusable prompt templates', 'Build content workflows with AI'],
                'audience' => ['Creators', 'Students', 'Small business'],
                'duration_minutes' => 35,
                'is_featured' => true,
                'status' => 'published',
                'published_at' => now(),
            ]
        );

        LearningLesson::updateOrCreate(
            ['slug' => 'prompt-basics-hindi'],
            [
                'learning_path_id' => $promptPath->id,
                'title' => 'Prompt basics: AI ko clear instruction kaise dein',
                'summary' => 'Role, task, context, format aur quality bar ka simple formula.',
                'content' => "Prompt ka kaam AI ko direction dena hai. Best prompt me 5 parts hote hain:\n\n1. Role: AI ko batao ki woh kis expert ki tarah answer de.\n2. Task: exactly kya output chahiye.\n3. Context: topic, audience, constraints.\n4. Format: bullets, table, script, caption, checklist.\n5. Quality bar: tone, length, examples, what to avoid.\n\nExample:\nAct as a Hindi YouTube scriptwriter. Topic: AI tools for students. Write a 60-second script in Hinglish with hook, 3 points, and CTA.",
                'action_steps' => ['Apne next content topic ke liye role + task + format prompt likho.', 'Same prompt ko short aur detailed version me test karo.', 'Best output ko prompt library me save karo.'],
                'resources' => ['Prompt Library: /prompts', 'AI Tool Finder: /ai-tool-finder'],
                'sort_order' => 1,
                'duration_minutes' => 8,
                'is_free' => true,
                'status' => 'published',
                'published_at' => now(),
            ]
        );

        LearningLesson::updateOrCreate(
            ['slug' => 'youtube-prompts-hindi'],
            [
                'learning_path_id' => $promptPath->id,
                'title' => 'YouTube prompts: hook, script aur title ka workflow',
                'summary' => 'Ek topic se YouTube title, hook, script aur CTA generate karna.',
                'content' => "Creators ke liye prompt workflow simple hona chahiye:\n\nStep 1: topic ko audience ke hisaab se narrow karo.\nStep 2: 10 title ideas generate karo.\nStep 3: best title ke liye 5 hooks banao.\nStep 4: final hook se 60-120 second script banao.\nStep 5: description, hashtags aur pinned comment banao.\n\nPrompt:\nAct as a Hindi YouTube growth strategist. Topic: [TOPIC]. Audience: beginners. Generate 10 titles, 5 hooks, and one 90-second script in Hinglish.",
                'action_steps' => ['YouTube Title Generator use karo.', 'Best 3 titles ko compare karo.', 'Ek selected title par script prompt run karo.'],
                'resources' => ['/tools/youtube-title-generator', '/prompts'],
                'sort_order' => 2,
                'duration_minutes' => 10,
                'is_free' => true,
                'status' => 'published',
                'published_at' => now(),
            ]
        );

        LearningLesson::updateOrCreate(
            ['slug' => 'prompt-framework-rtcf'],
            [
                'learning_path_id' => $promptPath->id,
                'title' => 'RTCF framework: Role, Task, Context, Format',
                'summary' => 'Har prompt ko clear banane ka repeatable framework.',
                'content' => "RTCF ek simple prompt framework hai:\n\nRole: AI kis expert ki tarah kaam kare?\nTask: output exactly kya chahiye?\nContext: audience, topic, constraints, examples.\nFormat: answer kis structure me chahiye?\n\nWeak prompt:\nAI tools par article likho.\n\nStrong prompt:\nAct as a Hindi tech explainer. Write a 700-word article on AI tools for students in India. Audience: beginners. Include 5 tools, use cases, pros/cons, and a short FAQ. Tone: simple Hinglish. Format: H2 sections + bullets.",
                'action_steps' => ['Ek weak prompt lo aur RTCF me rewrite karo.', 'Same prompt ko 3 formats me test karo: table, article, script.', 'Best version ko Prompt Library me save karo.'],
                'resources' => ['/prompts', '/tools/headline-generator'],
                'sort_order' => 3,
                'duration_minutes' => 8,
                'is_free' => true,
                'status' => 'published',
                'published_at' => now(),
            ]
        );

        LearningLesson::updateOrCreate(
            ['slug' => 'fact-checking-ai-output'],
            [
                'learning_path_id' => $promptPath->id,
                'title' => 'AI output ko fact-check kaise karein',
                'summary' => 'Publishing se pehle AI hallucination aur weak claims catch karna.',
                'content' => "AI useful hai, lekin har output publish-ready nahi hota. Fact-checking workflow:\n\n1. Claims identify karo: dates, numbers, names, prices, policies.\n2. Source demand karo: AI se source list mangna enough nahi, original source open karke verify karo.\n3. Sensitive topics me official sources use karo.\n4. Opinion aur fact ko separate rakho.\n5. Final article me source references add karo.\n\nFact-check prompt:\nReview this draft for factual claims. Create a table with claim, risk level, what source is needed, and suggested rewrite.",
                'action_steps' => ['Ek AI draft me 5 factual claims mark karo.', 'Har claim ke liye source type likho.', 'Unsafe claims ko neutral wording me rewrite karo.'],
                'resources' => ['/fact-check', '/editorial-policy'],
                'sort_order' => 4,
                'duration_minutes' => 9,
                'is_free' => true,
                'status' => 'published',
                'published_at' => now(),
            ]
        );

        LearningLesson::updateOrCreate(
            ['slug' => 'prompt-library-workflow'],
            [
                'learning_path_id' => $promptPath->id,
                'title' => 'Apni prompt library ka workflow banayein',
                'summary' => 'Reusable prompts ko organize karke fast content production system banana.',
                'content' => "Prompt library ek productivity asset hai. Isko random notes ki tarah mat rakho.\n\nCategories banao:\n- YouTube\n- Instagram\n- Blog/SEO\n- Resume/Jobs\n- Business\n- Research\n\nHar prompt ke saath store karo:\n- Use case\n- Audience\n- Input fields\n- Expected output\n- Best example output\n\nWeekly improvement:\nJo prompt weak output de, usme context aur examples add karo.",
                'action_steps' => ['5 reusable prompts save karo.', 'Har prompt me placeholder fields add karo.', 'Best performing prompt ko featured mark karo.'],
                'resources' => ['/prompts', '/daily-ai-brief'],
                'sort_order' => 5,
                'duration_minutes' => 7,
                'is_free' => true,
                'status' => 'published',
                'published_at' => now(),
            ]
        );

        AiSkillGuide::updateOrCreate(
            ['slug' => 'ai-skills-for-beginners-india'],
            [
                'title' => 'AI skills for beginners in India',
                'category' => 'AI Jobs',
                'career_stage' => 'beginner',
                'summary' => 'Prompting, research, content workflows, spreadsheets, and automation skills se AI career ka start karein.',
                'body' => "AI career start karne ke liye coding alone zaruri nahi hai. Beginners ke liye sabse practical route hai: prompts, research, content workflows, spreadsheet automation, and portfolio projects.\n\nFocus karo skill proof par: 3-5 projects banao, before/after results dikhao, aur tools ka responsible use seekho.",
                'skills' => ['Prompt writing', 'AI research', 'Content workflow design', 'Spreadsheet automation', 'Fact-checking'],
                'tools' => ['ChatGPT', 'Perplexity', 'Canva AI', 'Google Sheets', 'Notion'],
                'projects' => ['Create a Hindi AI tools directory spreadsheet', 'Build 20 reusable prompts for creators', 'Make a daily AI brief workflow', 'Automate resume bullet rewriting'],
                'roadmap' => ['Week 1: Learn prompt basics', 'Week 2: Practice research and summaries', 'Week 3: Build content workflows', 'Week 4: Publish portfolio projects'],
                'faqs' => [
                    ['question' => 'AI job ke liye coding zaruri hai?', 'answer' => 'Har role ke liye coding zaruri nahi. Prompting, research, content, automation, and tool operations roles me non-coders bhi start kar sakte hain.'],
                    ['question' => 'Beginner ko kaunsa tool pehle seekhna chahiye?', 'answer' => 'ChatGPT ya Gemini se prompt basics start karein, phir Perplexity se research workflow seekhein.'],
                ],
                'is_featured' => true,
                'status' => 'published',
                'published_at' => now(),
            ]
        );

        WebStory::updateOrCreate(
            ['slug' => 'ai-news-quick-guide'],
            [
                'author_id' => $admin->id,
                'title' => 'AI News: 5-Second Guide',
                'locale' => 'hi',
                'cover_image' => '/images/story-cover.jpg',
                'pages' => [
                    ['text' => 'AI + Human = Trusted News', 'image' => '/images/s1.jpg'],
                    ['text' => 'Optimized for Google Discover', 'image' => '/images/s2.jpg'],
                ],
                'status' => 'published',
                'published_at' => now(),
            ]
        );
    }
}
