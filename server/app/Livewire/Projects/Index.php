<?php

namespace App\Livewire\Projects;

use App\Models\Company;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Projekty')]
class Index extends Component
{
    public ?int $companyId = null;

    public string $companyName = '';

    public string $companyColor = 'zinc';

    public ?int $projectCompanyId = null;

    public string $projectName = '';

    /** @return Collection<int, Company> */
    #[Computed]
    public function companies(): Collection
    {
        return Auth::user()->companies()
            ->with(['projects' => fn ($q) => $q->withCount(['tasks' => fn ($t) => $t->open()])->orderBy('archived_at')->orderBy('name')])
            ->orderBy('name')
            ->get();
    }

    public function editCompany(?int $id = null): void
    {
        $this->resetValidation();
        $company = $id ? Auth::user()->companies()->findOrFail($id) : null;
        $this->companyId = $company?->id;
        $this->companyName = $company->name ?? '';
        $this->companyColor = $company->color ?? 'zinc';

        Flux::modal('company-form')->show();
    }

    public function saveCompany(): void
    {
        $data = $this->validate([
            'companyName' => ['required', 'string', 'max:255'],
            'companyColor' => ['required', Rule::in(Company::COLORS)],
        ]);

        Auth::user()->companies()->updateOrCreate(
            ['id' => $this->companyId],
            ['name' => $data['companyName'], 'color' => $data['companyColor']],
        );

        Flux::modal('company-form')->close();
        unset($this->companies);
    }

    public function newProject(int $companyId): void
    {
        $this->resetValidation();
        $this->projectCompanyId = Auth::user()->companies()->findOrFail($companyId)->id;
        $this->projectName = '';

        Flux::modal('project-form')->show();
    }

    public function saveProject(): void
    {
        $this->validate(['projectName' => ['required', 'string', 'max:255']]);

        $project = Auth::user()->projects()->create([
            'company_id' => Auth::user()->companies()->findOrFail($this->projectCompanyId)->id,
            'name' => $this->projectName,
        ]);

        Flux::modal('project-form')->close();
        $this->redirectRoute('projects.show', $project, navigate: true);
    }

    public function render(): View
    {
        return view('livewire.projects.index');
    }
}
