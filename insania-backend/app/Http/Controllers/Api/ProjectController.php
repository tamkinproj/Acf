<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Support\ApiResponse;
use App\Support\Audit;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    public function index()
    {
        return ApiResponse::collection(Project::orderBy('id')->get()->map->toApi());
    }

    public function store(Request $request)
    {
        $project = Project::create(['foundation_id' => $request->user()->foundation_id] + $request->validate($this->rules(true)));
        Audit::record('project.created', $request, 'project', $project->id);

        return ApiResponse::created($project->toApi());
    }

    public function update(Request $request, Project $project)
    {
        $project->update($request->validate($this->rules(false)));
        Audit::record('project.updated', $request, 'project', $project->id);

        return ApiResponse::updated($project->toApi());
    }

    private function rules(bool $creating): array
    {
        $req = $creating ? 'required' : 'sometimes';

        return [
            'name_en' => [$req, 'string', 'max:150'],
            'name_ar' => ['nullable', 'string', 'max:150'],
            'location' => ['nullable', 'string', 'max:150'],
            'budget' => ['sometimes', 'numeric', 'min:0', 'max:100000000'],
            'spent' => ['sometimes', 'numeric', 'min:0', 'max:100000000'],
            'status' => ['sometimes', 'in:active,completed'],
        ];
    }
}
