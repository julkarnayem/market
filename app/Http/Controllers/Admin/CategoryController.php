<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\CategoryAttribute;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class CategoryController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index()
    {
        $this->authorize('categories.manage');
        $categories = Category::roots()->with('children.attributes')->orderBy('position')->get();

        return Inertia::render('Admin/Categories/Index', [
            'categories' => $categories->map(fn (Category $cat) => [
                'id'            => $cat->id,
                'name'          => $cat->name,
                'icon'          => $cat->icon,
                'position'      => $cat->position,
                'is_active'     => $cat->is_active,
                'is_prohibited' => $cat->is_prohibited,
                'is_restricted' => $cat->is_restricted,
                'edit_url'      => route('admin.categories.edit', $cat),
                'children'      => $cat->children->map(fn (Category $sub) => [
                    'id'            => $sub->id,
                    'name'          => $sub->name,
                    'is_active'     => $sub->is_active,
                    'is_prohibited' => $sub->is_prohibited,
                    'is_restricted' => $sub->is_restricted,
                    'attr_count'    => $sub->attributes->count(),
                    'edit_url'      => route('admin.categories.edit', $sub),
                ])->all(),
            ])->all(),
        ]);
    }

    public function create()
    {
        $this->authorize('categories.manage');
        $parents = Category::roots()->active()->orderBy('position')->get();

        return Inertia::render('Admin/Categories/Create', [
            'parents' => $parents->map(fn (Category $p) => [
                'id'   => $p->id,
                'name' => $p->name,
            ])->all(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('categories.manage');
        $data = $request->validate([
            'name'          => 'required|string|max:100',
            'parent_id'     => 'nullable|integer|exists:categories,id',
            'description'   => 'nullable|string|max:500',
            'icon'          => 'nullable|string|max:50',
            'is_active'     => 'boolean',
            'is_prohibited' => 'boolean',
            'is_restricted' => 'boolean',
            'position'      => 'nullable|integer|min:0',
        ]);
        $data['slug'] = $this->uniqueSlug($data['name']);
        $cat = Category::create($data);
        $this->audit->log('category.created', $cat, [], $data);
        return redirect()->route('admin.categories')->with('success','Category created.');
    }

    public function edit(Category $category)
    {
        $this->authorize('categories.manage');
        $parents = Category::roots()->where('id', '!=', $category->id)->orderBy('position')->get();
        $attrs   = $category->attributes()->orderBy('position')->get();

        return Inertia::render('Admin/Categories/Edit', [
            'category' => [
                'id'            => $category->id,
                'name'          => $category->name,
                'parent_id'     => $category->parent_id,
                'icon'          => $category->icon,
                'description'   => $category->description,
                'position'      => $category->position,
                'is_active'     => $category->is_active,
                'is_prohibited' => $category->is_prohibited,
                'is_restricted' => $category->is_restricted,
            ],
            'parents' => $parents->map(fn (Category $p) => [
                'id'   => $p->id,
                'name' => $p->name,
            ])->all(),
            'attributes' => $attrs->map(fn (CategoryAttribute $a) => [
                'id'          => $a->id,
                'label'       => $a->label,
                'key'         => $a->key,
                'type'        => $a->type,
                'is_required' => $a->is_required,
                'is_active'   => $a->is_active,
            ])->all(),
            'attributeTypes' => CategoryAttribute::TYPES,
        ]);
    }

    public function update(Request $request, Category $category)
    {
        $this->authorize('categories.manage');
        $data = $request->validate([
            'name'          => 'required|string|max:100',
            'parent_id'     => 'nullable|integer|exists:categories,id',
            'description'   => 'nullable|string|max:500',
            'icon'          => 'nullable|string|max:50',
            'is_active'     => 'boolean',
            'is_prohibited' => 'boolean',
            'is_restricted' => 'boolean',
            'position'      => 'nullable|integer|min:0',
        ]);
        $old = $category->toArray();
        $category->update($data);
        $this->audit->log('category.updated', $category, $old, $data);
        return redirect()->route('admin.categories')->with('success','Category updated.');
    }

    public function deactivate(Category $category)
    {
        $this->authorize('categories.manage');
        $category->update(['is_active' => false]);
        return back()->with('success','Category deactivated.');
    }

    // Attribute management
    public function storeAttribute(Request $request, Category $category)
    {
        $this->authorize('categories.manage');
        $data = $request->validate([
            'label'            => 'required|string|max:100',
            'key'              => 'required|string|max:60|alpha_dash',
            'type'             => 'required|in:'.implode(',',CategoryAttribute::TYPES),
            'options'          => 'nullable|string',
            'is_required'      => 'boolean',
            'is_filterable'    => 'boolean',
            'placeholder'      => 'nullable|string|max:200',
            'unit'             => 'nullable|string|max:30',
            'validation_rules' => 'nullable|string|max:200',
            'position'         => 'nullable|integer|min:0',
        ]);
        // Parse options
        if (!empty($data['options'])) {
            $data['options'] = array_filter(array_map('trim', explode("\n", $data['options'])));
        }
        $category->attributes()->create($data);
        $this->audit->log('category_attribute.created', $category);
        return back()->with('success','Attribute added.');
    }

    public function updateAttribute(Request $request, Category $category, CategoryAttribute $attribute)
    {
        $this->authorize('categories.manage');
        abort_unless($attribute->category_id === $category->id, 403);
        $data = $request->validate([
            'label'         => 'required|string|max:100',
            'is_required'   => 'boolean',
            'is_filterable' => 'boolean',
            'is_active'     => 'boolean',
            'placeholder'   => 'nullable|string|max:200',
            'unit'          => 'nullable|string|max:30',
            'position'      => 'nullable|integer|min:0',
            'options'       => 'nullable|string',
        ]);
        if (!empty($data['options'])) {
            $data['options'] = array_filter(array_map('trim', explode("\n", $data['options'])));
        }
        $attribute->update($data);
        return back()->with('success','Attribute updated.');
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name); $slug = $base; $i = 2;
        while (Category::where('slug',$slug)->exists()) $slug = $base.'-'.$i++;
        return $slug;
    }
}
