<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleSession;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Documents véhicule (agrément, CT, carte grise…) et personnel (diplômes,
 * autorisations ARS, permis…). Gérés par les administrateurs, consultables
 * pendant un service avec journal (motif) et consentement individuel.
 */
class DocumentController extends Controller
{
    public function __construct(private readonly TenantContext $tenant) {}

    /** Ajout d'un document (administrateur — permission documents.manage). */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'subject_type' => ['required', Rule::in(['vehicle', 'user'])],
            'subject_id' => ['required', 'integer'],
            'category' => ['required', 'string', 'max:50'],
            'title' => ['required', 'string', 'max:200'],
            'expires_at' => ['nullable', 'date'],
            'file' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,webp'],
        ]);

        $subject = $validated['subject_type'] === 'vehicle'
            ? Vehicle::query()->findOrFail($validated['subject_id'])
            : User::query()->findOrFail($validated['subject_id']);

        $file = $request->file('file');
        $path = $file->store("documents/{$this->tenant->id()}", 'local');

        $subject->documents()->create([
            'category' => $validated['category'],
            'title' => $validated['title'],
            'file_path' => $path,
            'mime' => $file->getClientMimeType(),
            'size' => $file->getSize(),
            'expires_at' => $validated['expires_at'] ?? null,
            'uploaded_by' => $request->user()->id,
        ]);

        return back()->with('status', 'Document ajouté.');
    }

    public function destroy(Document $document): RedirectResponse
    {
        if ($document->file_path && Storage::disk('local')->exists($document->file_path)) {
            Storage::disk('local')->delete($document->file_path);
        }
        $document->delete();

        return back()->with('status', 'Document supprimé.');
    }

    /** Enregistre une consultation (motif) avant l'ouverture du document. */
    public function consult(Request $request, Document $document): RedirectResponse
    {
        abort_unless($this->canConsult($document, $request->user()), 403);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:200'],
        ]);

        $document->accesses()->create([
            'user_id' => $request->user()->id,
            'reason' => $validated['reason'],
            'consulted_at' => now(),
        ]);

        return back(303)->with('status', 'Consultation enregistrée.');
    }

    /** Sert le fichier du document (disque privé), après contrôle d'accès. */
    public function file(Request $request, Document $document)
    {
        abort_unless($this->canConsult($document, $request->user()), 403);
        abort_unless($document->file_path && Storage::disk('local')->exists($document->file_path), 404);

        return response()->file(Storage::disk('local')->path($document->file_path));
    }

    /** L'utilisateur autorise (ou retire) la consultation de ses documents. */
    public function consent(Request $request): RedirectResponse
    {
        $user = $request->user();
        $user->documents_consent = $request->boolean('consent');
        $user->save();

        return back()->with('status', $user->documents_consent ? 'Consultation autorisée.' : 'Consultation retirée.');
    }

    /**
     * Règles d'accès : l'admin voit tout ; un document véhicule est consultable
     * par un agent en service sur ce véhicule (ou un gestionnaire) ; un document
     * personnel n'est consultable que par son titulaire ayant donné son
     * consentement.
     */
    private function canConsult(Document $document, User $user): bool
    {
        if ($user->can('documents.manage')) {
            return true;
        }

        if ($document->documentable_type === Vehicle::class) {
            if ($user->can('vehicles.manage')) {
                return true;
            }

            return VehicleSession::query()->open()
                ->where('vehicle_id', $document->documentable_id)
                ->where('user_id', $user->id)
                ->exists();
        }

        if ($document->documentable_type === User::class) {
            return $document->documentable_id === $user->id && (bool) $user->documents_consent;
        }

        return false;
    }
}
