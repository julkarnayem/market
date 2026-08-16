<?php
namespace Database\Seeders;

use App\Models\Category;
use App\Models\CategoryAttribute;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        // Clear existing
        CategoryAttribute::query()->delete();
        Category::query()->delete();

        $tree = [
            ['Social Media', '📱', [
                ['YouTube', '▶️', [
                    ['Subscribers',           'subscribers',       'number',     '',       false],
                    ['Monetization Status',   'monetization',      'select',     '',       false, ['Yes','No','Pending']],
                    ['Channel Age',           'channel_age',       'text',       '',       false],
                    ['Country',               'country',           'text',       '',       false],
                    ['Niche',                 'niche',             'text',       '',       false],
                    ['Average Views',         'avg_views',         'number',     '',       false],
                    ['Monthly Revenue',       'monthly_revenue',   'currency',   'BDT',    false],
                    ['Content Language',      'language',          'text',       '',       false],
                ]],
                ['Facebook Page', '📘', [
                    ['Followers',             'followers',         'number',     '',       false],
                    ['Page Age',              'page_age',          'text',       '',       false],
                    ['Country',               'country',           'text',       '',       false],
                    ['Niche',                 'niche',             'text',       '',       false],
                    ['Monthly Reach',         'monthly_reach',     'number',     '',       false],
                    ['Monetization',          'monetization',      'select',     '',       false, ['Yes','No','Pending']],
                    ['Engagement Rate',       'engagement_rate',   'percentage', '',       false],
                ]],
                ['Facebook Group', '👥', [
                    ['Members',               'members',           'number',     '',       false],
                    ['Group Age',             'group_age',         'text',       '',       false],
                    ['Country',               'country',           'text',       '',       false],
                    ['Niche',                 'niche',             'text',       '',       false],
                    ['Monthly Posts',         'monthly_posts',     'number',     '',       false],
                ]],
                ['Instagram', '📸', [
                    ['Followers',             'followers',         'number',     '',       false],
                    ['Following',             'following',         'number',     '',       false],
                    ['Account Age',           'account_age',       'text',       '',       false],
                    ['Country',               'country',           'text',       '',       false],
                    ['Niche',                 'niche',             'text',       '',       false],
                    ['Engagement Rate',       'engagement_rate',   'percentage', '',       false],
                    ['Average Reel Views',    'avg_reel_views',    'number',     '',       false],
                ]],
                ['TikTok', '🎵', [
                    ['Followers',             'followers',         'number',     '',       false],
                    ['Account Age',           'account_age',       'text',       '',       false],
                    ['Country',               'country',           'text',       '',       false],
                    ['Niche',                 'niche',             'text',       '',       false],
                    ['Average Views',         'avg_views',         'number',     '',       false],
                    ['Monthly Revenue',       'monthly_revenue',   'currency',   'BDT',    false],
                ]],
                ['Telegram Channel', '✈️', [
                    ['Subscribers',           'subscribers',       'number',     '',       false],
                    ['Channel Age',           'channel_age',       'text',       '',       false],
                    ['Niche',                 'niche',             'text',       '',       false],
                    ['Average Views',         'avg_views',         'number',     '',       false],
                ]],
                ['Telegram Group', '💬', [
                    ['Members',               'members',           'number',     '',       false],
                    ['Group Age',             'group_age',         'text',       '',       false],
                    ['Niche',                 'niche',             'text',       '',       false],
                ]],
                ['X / Twitter', '𝕏', [
                    ['Followers',             'followers',         'number',     '',       false],
                    ['Account Age',           'account_age',       'text',       '',       false],
                    ['Country',               'country',           'text',       '',       false],
                    ['Niche',                 'niche',             'text',       '',       false],
                ]],
                ['LinkedIn', '💼', [
                    ['Followers',             'followers',         'number',     '',       false],
                    ['Account Age',           'account_age',       'text',       '',       false],
                    ['Niche',                 'niche',             'text',       '',       false],
                ]],
                ['Snapchat', '👻', [
                    ['Followers',             'followers',         'number',     '',       false],
                    ['Account Age',           'account_age',       'text',       '',       false],
                ]],
                ['Pinterest', '📌', [
                    ['Followers',             'followers',         'number',     '',       false],
                    ['Monthly Views',         'monthly_views',     'number',     '',       false],
                ]],
                ['Other Social Media', '🌐', [
                    ['Platform Name',         'platform_name',     'text',       '',       false],
                    ['Followers/Members',     'followers',         'number',     '',       false],
                ]],
            ]],

            ['Gaming', '🎮', [
                ['PUBG', '🔫', [
                    ['Platform',              'platform',          'select',     '',       false, ['Mobile','PC','Console']],
                    ['Region',                'region',            'text',       '',       false],
                    ['Account Level',         'level',             'number',     '',       false],
                    ['Rank',                  'rank',              'text',       '',       false],
                    ['K/D Ratio',             'kd_ratio',          'decimal',    '',       false],
                    ['Skins',                 'skins',             'text',       '',       false],
                    ['Rare Items',            'rare_items',        'text',       '',       false],
                ]],
                ['Free Fire', '🔥', [
                    ['Account Level',         'level',             'number',     '',       false],
                    ['Rank',                  'rank',              'text',       '',       false],
                    ['Diamonds',              'diamonds',          'number',     '',       false],
                    ['Skins',                 'skins',             'text',       '',       false],
                ]],
                ['Call of Duty', '🎯', [
                    ['Platform',              'platform',          'select',     '',       false, ['Mobile','PC','Console']],
                    ['Account Level',         'level',             'number',     '',       false],
                    ['K/D Ratio',             'kd_ratio',          'decimal',    '',       false],
                    ['Rare Items',            'rare_items',        'text',       '',       false],
                ]],
                ['Fortnite', '⚡', [
                    ['Account Level',         'level',             'number',     '',       false],
                    ['Skins',                 'skins',             'text',       '',       false],
                    ['V-Bucks',               'vbucks',            'number',     '',       false],
                ]],
                ['Roblox', '🧱', [
                    ['Robux Balance',         'robux',             'number',     '',       false],
                    ['Account Age',           'account_age',       'text',       '',       false],
                    ['Rare Items',            'rare_items',        'text',       '',       false],
                ]],
                ['Minecraft', '⛏️', [
                    ['Edition',               'edition',           'select',     '',       false, ['Java','Bedrock','Both']],
                    ['Account Age',           'account_age',       'text',       '',       false],
                ]],
                ['Other Games', '🕹️', [
                    ['Game Name',             'game_name',         'text',       '',       false],
                    ['Account Level',         'level',             'number',     '',       false],
                    ['Platform',              'platform',          'text',       '',       false],
                ]],
            ]],

            ['Websites', '🌐', [
                ['WordPress', '📝', [
                    ['Domain',                'domain',            'url',        '',       false],
                    ['Domain Age',            'domain_age',        'text',       '',       false],
                    ['Niche',                 'niche',             'text',       '',       false],
                    ['Monthly Visitors',      'monthly_visitors',  'number',     '',       false],
                    ['Monthly Revenue',       'monthly_revenue',   'currency',   'BDT',    false],
                    ['Traffic Source',        'traffic_source',    'text',       '',       false],
                    ['DA',                    'domain_authority',  'number',     '',       false],
                ]],
                ['E-commerce', '🛒', [
                    ['Domain',                'domain',            'url',        '',       false],
                    ['Platform',              'platform',          'select',     '',       false, ['WooCommerce','Shopify','Custom','Other']],
                    ['Monthly Revenue',       'monthly_revenue',   'currency',   'BDT',    false],
                    ['Monthly Orders',        'monthly_orders',    'number',     '',       false],
                    ['Niche',                 'niche',             'text',       '',       false],
                ]],
                ['News Portal', '📰', [
                    ['Domain',                'domain',            'url',        '',       false],
                    ['Monthly Visitors',      'monthly_visitors',  'number',     '',       false],
                    ['Monthly Revenue',       'monthly_revenue',   'currency',   'BDT',    false],
                    ['Niche',                 'niche',             'text',       '',       false],
                ]],
                ['Blog', '✍️', [
                    ['Domain',                'domain',            'url',        '',       false],
                    ['Monthly Visitors',      'monthly_visitors',  'number',     '',       false],
                    ['Niche',                 'niche',             'text',       '',       false],
                    ['Monetization',          'monetization',      'text',       '',       false],
                ]],
                ['Niche Website', '🎯', [
                    ['Domain',                'domain',            'url',        '',       false],
                    ['Niche',                 'niche',             'text',       '',       false],
                    ['Monthly Visitors',      'monthly_visitors',  'number',     '',       false],
                    ['Monthly Revenue',       'monthly_revenue',   'currency',   'BDT',    false],
                ]],
                ['Landing Page', '🚀', [
                    ['Domain',                'domain',            'url',        '',       false],
                    ['Niche',                 'niche',             'text',       '',       false],
                ]],
                ['Forum / Community Website', '💬', [
                    ['Domain',                'domain',            'url',        '',       false],
                    ['Members',               'members',           'number',     '',       false],
                    ['Monthly Visitors',      'monthly_visitors',  'number',     '',       false],
                ]],
                ['SaaS Website', '⚙️', [
                    ['Domain',                'domain',            'url',        '',       false],
                    ['Monthly Revenue',       'monthly_revenue',   'currency',   'BDT',    false],
                    ['Monthly Active Users',  'mau',               'number',     '',       false],
                    ['Tech Stack',            'tech_stack',        'text',       '',       false],
                ]],
                ['Other Websites', '🌍', [
                    ['Domain',                'domain',            'url',        '',       false],
                    ['Monthly Visitors',      'monthly_visitors',  'number',     '',       false],
                ]],
            ]],

            ['Domains', '🔗', [
                ['.com', '🌐', [
                    ['Domain Age',            'domain_age',        'text',       '',       false],
                    ['DA',                    'domain_authority',  'number',     '',       false],
                ]],
                ['.com.bd', '🇧🇩', [
                    ['Domain Age',            'domain_age',        'text',       '',       false],
                ]],
                ['.net', '📡', [
                    ['Domain Age',            'domain_age',        'text',       '',       false],
                ]],
                ['.org', '🏛️', [
                    ['Domain Age',            'domain_age',        'text',       '',       false],
                ]],
                ['Country TLD', '🏴', [
                    ['TLD',                   'tld',               'text',       '',       false],
                    ['Domain Age',            'domain_age',        'text',       '',       false],
                ]],
                ['Premium Domain', '💎', [
                    ['Domain Age',            'domain_age',        'text',       '',       false],
                    ['Price Justification',   'price_justification','text',      '',       false],
                ]],
                ['Aged Domain', '⏳', [
                    ['Domain Age',            'domain_age',        'text',       '',       false],
                    ['DA',                    'domain_authority',  'number',     '',       false],
                    ['Backlinks',             'backlinks',         'number',     '',       false],
                ]],
                ['Other TLD', '🔤', [
                    ['TLD',                   'tld',               'text',       '',       false],
                    ['Domain Age',            'domain_age',        'text',       '',       false],
                ]],
            ]],

            ['Apps & Software', '💻', [
                ['Android App', '🤖', [
                    ['Downloads',             'downloads',         'number',     '',       false],
                    ['Rating',                'rating',            'decimal',    '',       false],
                    ['Monthly Revenue',       'monthly_revenue',   'currency',   'BDT',    false],
                    ['Niche',                 'niche',             'text',       '',       false],
                    ['Monetization',          'monetization',      'text',       '',       false],
                ]],
                ['iOS App', '🍎', [
                    ['Downloads',             'downloads',         'number',     '',       false],
                    ['Rating',                'rating',            'decimal',    '',       false],
                    ['Monthly Revenue',       'monthly_revenue',   'currency',   'BDT',    false],
                    ['Niche',                 'niche',             'text',       '',       false],
                ]],
                ['Web App', '🌐', [
                    ['Monthly Active Users',  'mau',               'number',     '',       false],
                    ['Monthly Revenue',       'monthly_revenue',   'currency',   'BDT',    false],
                    ['Tech Stack',            'tech_stack',        'text',       '',       false],
                ]],
                ['SaaS', '☁️', [
                    ['Monthly Revenue',       'monthly_revenue',   'currency',   'BDT',    false],
                    ['Monthly Active Users',  'mau',               'number',     '',       false],
                    ['Tech Stack',            'tech_stack',        'text',       '',       false],
                ]],
                ['Script', '📜', [
                    ['Language',              'language',          'text',       '',       false],
                    ['License',               'license',           'select',     '',       false, ['GPL','MIT','Commercial','Proprietary']],
                ]],
                ['Plugin', '🔌', [
                    ['Platform',              'platform',          'select',     '',       false, ['WordPress','Shopify','WooCommerce','Other']],
                    ['License',               'license',           'select',     '',       false, ['GPL','MIT','Commercial']],
                ]],
                ['Theme', '🎨', [
                    ['Platform',              'platform',          'select',     '',       false, ['WordPress','Shopify','HTML','Other']],
                    ['License',               'license',           'select',     '',       false, ['GPL','MIT','Commercial']],
                ]],
                ['Software', '🖥️', [
                    ['Platform',              'platform',          'select',     '',       false, ['Windows','Mac','Linux','Cross-platform']],
                    ['License',               'license',           'text',       '',       false],
                ]],
                ['Other', '📦', [
                    ['Description',           'extra_info',        'textarea',   '',       false],
                ]],
            ]],

            ['Digital Products', '📦', [
                ['E-book', '📖', [
                    ['Topic/Niche',           'niche',             'text',       '',       false],
                    ['Pages',                 'pages',             'number',     '',       false],
                    ['Language',              'language',          'text',       '',       false],
                    ['Format',                'format',            'select',     '',       false, ['PDF','EPUB','MOBI','Word']],
                ]],
                ['Course', '🎓', [
                    ['Topic',                 'niche',             'text',       '',       false],
                    ['Duration',              'duration',          'text',       '',       false],
                    ['Language',              'language',          'text',       '',       false],
                ]],
                ['Template', '📋', [
                    ['Platform',              'platform',          'text',       '',       false],
                    ['Format',                'format',            'text',       '',       false],
                ]],
                ['Graphics', '🎨', [
                    ['Format',                'format',            'text',       '',       false],
                    ['Resolution',            'resolution',        'text',       '',       false],
                ]],
                ['UI/UX', '🖌️', [
                    ['Format',                'format',            'text',       '',       false],
                    ['Platform',              'platform',          'text',       '',       false],
                ]],
                ['Digital Files', '📁', [
                    ['Format',                'format',            'text',       '',       false],
                ]],
                ['Other Digital Products', '✨', [
                    ['Description',           'extra_info',        'textarea',   '',       false],
                ]],
            ]],

            ['Communities', '👥', [
                ['Discord', '🎮', [
                    ['Members',               'members',           'number',     '',       false],
                    ['Niche',                 'niche',             'text',       '',       false],
                    ['Server Age',            'account_age',       'text',       '',       false],
                ]],
                ['Reddit', '🔴', [
                    ['Members',               'members',           'number',     '',       false],
                    ['Subreddit Age',         'account_age',       'text',       '',       false],
                ]],
                ['Forum', '💬', [
                    ['Members',               'members',           'number',     '',       false],
                    ['Niche',                 'niche',             'text',       '',       false],
                    ['Monthly Visitors',      'monthly_visitors',  'number',     '',       false],
                ]],
                ['Online Community', '🌐', [
                    ['Members',               'members',           'number',     '',       false],
                    ['Platform',              'platform',          'text',       '',       false],
                ]],
                ['Other Communities', '🏘️', [
                    ['Platform',              'platform',          'text',       '',       false],
                    ['Members',               'members',           'number',     '',       false],
                ]],
            ]],

            ['Other Digital Assets', '💡', [
                ['Other', '📌', [
                    ['Description',           'extra_info',        'textarea',   '',       false],
                ]],
            ]],
        ];

        foreach ($tree as $pos => [$name, $icon, $children]) {
            $parent = Category::create([
                'name'      => $name,
                'slug'      => Str::slug($name),
                'icon'      => $icon,
                'is_active' => true,
                'position'  => $pos,
            ]);

            foreach ($children as $cPos => [$cName, $cIcon, $attrs]) {
                $child = Category::create([
                    'parent_id' => $parent->id,
                    'name'      => $cName,
                    'slug'      => Str::slug($cName).'-'.$parent->id,
                    'icon'      => $cIcon,
                    'is_active' => true,
                    'position'  => $cPos,
                ]);

                foreach ($attrs as $aPos => $attr) {
                    CategoryAttribute::create([
                        'category_id' => $child->id,
                        'label'       => $attr[0],
                        'key'         => $attr[1],
                        'type'        => $attr[2],
                        'unit'        => $attr[3] ?? '',
                        'is_required' => false, // ALL optional per spec
                        'is_filterable' => in_array($attr[1], ['subscribers','followers','members','monthly_revenue','monthly_visitors','monetization','rank','level','platform','country','niche']),
                        'position'    => $aPos,
                        'is_active'   => true,
                        'options'     => isset($attr[5]) ? $attr[5] : null,
                    ]);
                }
            }
        }
    }
}
