<?php
namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\Category;
use Illuminate\Http\Request;

class PageController extends Controller
{
    public function home()
    {
        $categories    = Category::roots()->active()->withCount('children')->orderBy('position')->get();
        $featuredAssets= Asset::published()->featuredNow()->with(['coverImage','seller','category'])->limit(8)->get();
        $latestAssets  = Asset::published()->with(['coverImage','seller','category'])->latest()->limit(8)->get();

        return view('pages.home', compact('categories','featuredAssets','latestAssets'));
    }

    public function legal(string $slug)
    {
        abort_unless(array_key_exists($slug, self::pages()), 404);
        return view('legal.show', ['page' => self::pages()[$slug]]);
    }

    public function contact() { return view('pages.contact'); }

    public function contactSubmit(Request $request)
    {
        $request->validate(['name'=>'required','email'=>'required|email','message'=>'required']);
        return back()->with('success', 'Thank you - your message has been received.');
    }

    public function faq() { return view('pages.faq'); }

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
