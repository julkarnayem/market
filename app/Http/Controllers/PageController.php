<?php
namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\MapsMarketplaceProps;
use App\Models\Asset;
use App\Models\Category;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PageController extends Controller
{
    use MapsMarketplaceProps;

    public function home()
    {
        $categories    = Category::roots()->active()->withCount('children')->orderBy('position')->get();
        $featuredAssets= Asset::published()->featuredNow()->with(['coverImage','seller','category'])->limit(8)->get();
        $latestAssets  = Asset::published()->with(['coverImage','seller','category'])->latest()->limit(8)->get();

        return Inertia::render('Home', [
            'categories'     => $categories->map(self::mapCategory())->all(),
            'featuredAssets' => $featuredAssets->map(self::mapAsset())->all(),
            'latestAssets'   => $latestAssets->map(self::mapAsset())->all(),
        ]);
    }

    public function legal(string $slug)
    {
        $pages = self::pages();
        abort_unless(array_key_exists($slug, $pages), 404);

        return Inertia::render('Legal', [
            'page' => [...$pages[$slug], 'slug' => $slug],
            // Formatted server-side so the date matches the app timezone
            // rather than the visitor's clock.
            'lastUpdated' => now()->format('F Y'),
        ]);
    }

    public function contact()
    {
        return Inertia::render('Contact');
    }

    public function contactSubmit(Request $request)
    {
        $request->validate(['name'=>'required','email'=>'required|email','message'=>'required']);
        return back()->with('success', 'Thank you - your message has been received.');
    }

    public function faq()
    {
        return Inertia::render('Faq', ['faqs' => self::faqs()]);
    }

    /**
     * FAQ content. Server-owned like self::pages() below, so copy edits stay
     * in PHP rather than in the compiled Vue bundle.
     *
     * @return list<array{question: string, answer: string}>
     */
    private static function faqs(): array
    {
        return [
            [
                'question' => 'Is there a fee to list an asset?',
                'answer'   => 'No. Creating a listing is completely free. Sellers pay a flat 10% platform fee only when an asset sells.',
            ],
            [
                'question' => 'How does buyer protection work?',
                'answer'   => 'After payment, funds are held and you have 72 hours to confirm delivery. If you do nothing, the order auto-completes.',
            ],
            [
                'question' => 'When can a seller withdraw earnings?',
                'answer'   => 'Earnings unlock 8 hours after an order is completed. The minimum withdrawal is ৳50 with a ৳5 fee, paid via Mobile Financial Services.',
            ],
            [
                'question' => 'Can I cancel after paying?',
                'answer'   => 'No. Once payment succeeds, orders cannot be cancelled by the buyer. Issues are handled through disputes.',
            ],
            [
                'question' => 'Do I need verification to buy?',
                'answer'   => 'No. Anyone can buy. Verification is only required to sell.',
            ],
        ];
    }

    private static function pages(): array
    {
        $p = fn($t,$b) => ['title'=>$t,'body'=>"<p>{$b}</p><p class='text-slate-400 text-sm mt-4'>Final legal text pending review.</p>"];
        return [
            'terms'            => $p('Terms of Service','By using this marketplace you agree to these terms.'),
            'privacy'          => $p('Privacy Policy','We handle your data securely and never expose verification documents publicly.'),
            'buyer-protection' => $p('Buyer Protection','Every paid order is protected for 72 hours before it auto-completes.'),
            'seller-policy'    => $p('Seller Policy','Sellers must be verified. A flat 10% platform fee applies to all sales.'),
            'refund-policy'    => $p('Refund Policy','Refunds are handled through the dispute process.'),
            'dispute-policy'   => $p('Dispute Policy','Open a dispute from an order page to request admin review.'),
            'prohibited-assets'=> $p('Prohibited Assets','Stolen, fraudulent, malicious, or infringing assets are strictly prohibited.'),
        ];
    }
}
