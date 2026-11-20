<?php
/**
 * What the public landing page lets the President Admin change, and the text it shows today.
 * The editor in the staff app is drawn from this file and the server validates every save against it.
 * Keys of 'texts' are the page's own text ids (data-i18n), so the page and the editor stay in step.
 */
return [
    'texts' => [
        'h_t1' => ['group' => 'home', 'label' => 'Headline, first part', 'long' => false, 'max' => 160, 'en' => 'Caring for every orphan,', 'ar' => 'نرعى كل يتيم،'],
        'h_t2' => ['group' => 'home', 'label' => 'Headline, highlighted part', 'long' => false, 'max' => 160, 'en' => 'with dignity and trust.', 'ar' => 'بكرامة وثقة.'],
        'h_sub' => ['group' => 'home', 'label' => 'Intro under the headline', 'long' => true, 'max' => 1500, 'en' => 'Al Insania Charity Foundation Inc. supports orphans and their families in the Philippines through programs sponsored by trusted partners in the UAE.', 'ar' => 'تدعم مؤسسة الإنسانية الخيرية الأيتام وأسرهم في الفلبين عبر برامج يكفلها شركاء موثوقون في الإمارات.'],
        'a_lead' => ['group' => 'about', 'label' => 'About, short intro', 'long' => true, 'max' => 1500, 'en' => '[Write 2 to 3 sentences: who you are, who you serve, and why you started.]', 'ar' => '[اكتب جملتين أو ثلاثًا: من أنتم، ومن تخدمون، ولماذا بدأتم.]'],
        'a_p' => ['group' => 'about', 'label' => 'About, our story', 'long' => true, 'max' => 1500, 'en' => '[Tell the story of the foundation: when it began, how it grew, and the people who made it possible.]', 'ar' => '[احكِ قصة المؤسسة: متى بدأت، وكيف نمت، ومن ساهم في ذلك.]'],
        'a_mp' => ['group' => 'about', 'label' => 'Mission', 'long' => true, 'max' => 1500, 'en' => '[Our mission in one sentence.]', 'ar' => '[رسالتنا في جملة واحدة.]'],
        'a_vp' => ['group' => 'about', 'label' => 'Vision', 'long' => true, 'max' => 1500, 'en' => '[Our vision in one sentence.]', 'ar' => '[رؤيتنا في جملة واحدة.]'],
        'a_valp' => ['group' => 'about', 'label' => 'Values', 'long' => true, 'max' => 1500, 'en' => '[Integrity, privacy, governance, care.]', 'ar' => '[النزاهة، الخصوصية، الحوكمة، الرعاية.]'],
        'a_f1v' => ['group' => 'facts', 'label' => 'Registered as', 'long' => false, 'max' => 160, 'en' => '[Legal status]', 'ar' => '[الصفة القانونية]'],
        'a_f3v' => ['group' => 'facts', 'label' => 'Supervised by', 'long' => false, 'max' => 160, 'en' => '[Authority name]', 'ar' => '[اسم الجهة]'],
        'a_f4v' => ['group' => 'facts', 'label' => 'Founded', 'long' => false, 'max' => 160, 'en' => '2016 (1437 AH)', 'ar' => '2016م (1437هـ)'],
        'a_f5v' => ['group' => 'facts', 'label' => 'Based in', 'long' => false, 'max' => 160, 'en' => 'Pagadian City, Philippines', 'ar' => 'مدينة باغاديان، الفلبين'],
        'ld_name' => ['group' => 'president', 'label' => 'President name', 'long' => false, 'max' => 160, 'en' => '[President\'s full name]', 'ar' => '[الاسم الكامل للرئيس]'],
        'ld_role' => ['group' => 'president', 'label' => 'President title', 'long' => false, 'max' => 160, 'en' => 'President', 'ar' => 'الرئيس'],
        'ld_msg' => ['group' => 'president', 'label' => 'Message from the president', 'long' => true, 'max' => 1500, 'en' => '[A short message from the president: who we serve and why the work matters. 2 to 4 sentences.]', 'ar' => '[كلمة قصيرة من الرئيس: من نخدم ولماذا يهمّ هذا العمل. من 2 إلى 4 جمل.]'],
        'c_adv' => ['group' => 'contact', 'label' => 'Address', 'long' => true, 'max' => 1500, 'en' => 'Zone 5 Tiguma, Pagadian City, Zamboanga del Sur, Philippines', 'ar' => 'المنطقة 5 تيغوما، مدينة باغاديان، زامبوانغا ديل سور، الفلبين'],
        'c_hrv' => ['group' => 'contact', 'label' => 'Opening hours', 'long' => false, 'max' => 160, 'en' => '[Monday to Friday, 8:00 to 17:00]', 'ar' => '[من الاثنين إلى الجمعة، 8:00 إلى 17:00]'],
        'ft_p' => ['group' => 'contact', 'label' => 'Footer sentence', 'long' => true, 'max' => 1500, 'en' => '[One sentence about the foundation.]', 'ar' => '[جملة واحدة عن المؤسسة.]'],
        'q' => ['group' => 'testimonial', 'label' => 'Testimonial', 'long' => true, 'max' => 1500, 'en' => '[A short testimonial from a guardian, sponsor or partner, with their permission.]', 'ar' => '[شهادة قصيرة من وصي أو كافل أو شريك، بإذنه.]'],
        'q_by' => ['group' => 'testimonial', 'label' => 'Testimonial, who said it', 'long' => false, 'max' => 160, 'en' => '[Name, role]', 'ar' => '[الاسم، الصفة]'],
    ],

    'groups' => [
        'home' => 'Home page',
        'about' => 'About us',
        'facts' => 'Foundation details',
        'president' => 'The president',
        'contact' => 'Contact and footer',
        'testimonial' => 'Testimonial',
    ],

    // One value for both languages.
    'singles' => [
        'phone' => ['label' => 'Phone', 'type' => 'phone', 'default' => '+63 000 000 0000'],
        'whatsapp' => ['label' => 'WhatsApp', 'type' => 'phone', 'default' => '+63 900 000 0000'],
        'email' => ['label' => 'Email', 'type' => 'email', 'default' => 'info@your-domain.com'],
        'license_no' => ['label' => 'Licence number', 'type' => 'text', 'default' => '000000'],
        'facebook' => ['label' => 'Facebook address', 'type' => 'url', 'default' => ''],
        'instagram' => ['label' => 'Instagram address', 'type' => 'url', 'default' => ''],
        'youtube' => ['label' => 'YouTube address', 'type' => 'url', 'default' => ''],
    ],

    'numbers' => [
        'hero_orphans' => ['label' => 'Orphans sponsored', 'default' => 120],
        'hero_families' => ['label' => 'Families reached', 'default' => 85],
        'hero_partners' => ['label' => 'Partner sponsors', 'default' => 2],
        'hero_years' => ['label' => 'Years of service', 'default' => 10],
        'proj_done' => ['label' => 'Projects completed', 'default' => 24],
        'proj_types' => ['label' => 'Types of projects', 'default' => 8],
        'proj_communities' => ['label' => 'Communities reached', 'default' => 15],
        'proj_people' => ['label' => 'People benefiting', 'default' => 800],
    ],

    'images' => ['president_photo' => ['label' => 'President photo']],

    'categories' => ['orphan', 'edu', 'alimni', 'waqf', 'water', 'mosque', 'relief', 'train'],

    'lists' => [
        'projects' => ['max' => 60, 'label' => 'Projects we have completed'],
        'team' => ['max' => 60, 'label' => 'Team and staff'],
        'news' => ['max' => 40, 'label' => 'News and announcements'],
    ],

    'limits' => ['max_json_bytes' => 400000, 'image_bytes' => 5242880, 'image_side' => 1600],
];
