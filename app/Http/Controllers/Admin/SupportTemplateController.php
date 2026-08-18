<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupportResponseTemplate;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class SupportTemplateController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index()
    {
        // Matches the permission both sidebars gate the nav item on.
        $this->authorize('tickets.manage');

        $groups = SupportResponseTemplate::with('creator')->latest()->get()
            // A null/empty category is its own bucket, as in the Blade.
            ->groupBy(fn (SupportResponseTemplate $t) => $t->category ?: 'General')
            ->sortKeys()
            ->map(fn (Collection $group, string $label) => [
                'label'     => $label,
                'templates' => $group->map(fn (SupportResponseTemplate $t) => [
                    'id'        => $t->id,
                    'title'     => $t->title,
                    'category'  => $t->category,
                    'body'      => $t->body,
                    'is_active' => (bool) $t->is_active,
                    'creator'   => $t->creator?->name,
                    'created'   => $t->created_at->format('d M Y'),
                ])->values()->all(),
            ])->values()->all();

        return Inertia::render('Admin/SupportTemplates/Index', [
            'groups' => $groups,
            // The real substitution list, so the form documents what render()
            // will actually replace instead of the Blade's "etc.".
            'variables' => SupportResponseTemplate::VARIABLES,
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('tickets.manage');
        $data = $request->validate([
            'title'    => 'required|string|max:200',
            'category' => 'nullable|string|max:50',
            'body'     => 'required|string|max:5000',
        ]);
        $t = SupportResponseTemplate::create([...$data, 'created_by' => Auth::id(), 'is_active' => true]);
        $this->audit->log('support_template.created', $t, [], [], '', 'support');
        return back()->with('success', 'Template created.');
    }

    public function update(Request $request, SupportResponseTemplate $template)
    {
        $this->authorize('tickets.manage');
        $data = $request->validate(['title'=>'required|string|max:200','category'=>'nullable|string','body'=>'required|string|max:5000','is_active'=>'boolean']);
        $old  = $template->toArray();
        $template->update($data);
        $this->audit->log('support_template.updated', $template, $old, $data, '', 'support');
        return back()->with('success', 'Template updated.');
    }

    /** JSON endpoint: fetch template body for insertion into reply form. */
    public function get(SupportResponseTemplate $template)
    {
        $this->authorize('tickets.manage');
        return response()->json(['body' => $template->body]);
    }
}
