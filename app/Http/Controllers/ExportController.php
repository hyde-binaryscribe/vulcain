<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Material;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Exports CSV (téléchargeables). BOM UTF-8 en tête pour un affichage correct
 * des accents dans Excel.
 */
class ExportController extends Controller
{
    public function materials(): StreamedResponse
    {
        $rows = Material::query()
            ->with(['category:id,name', 'location:id,name,parent_id,vehicle_id', 'location.vehicle:id,name', 'location.parent:id,name,parent_id,vehicle_id', 'lots:id,material_id,quantity', 'items:id,material_id'])
            ->orderBy('name')
            ->get();

        return $this->stream('materiel.csv', ['Nom', 'Référence', 'Catégorie', 'Emplacement', 'Suivi', 'Stock', 'Seuil', 'Statut'], function ($out) use ($rows) {
            foreach ($rows as $m) {
                fputcsv($out, [
                    $m->name,
                    $m->reference,
                    $m->category?->name,
                    $m->location?->fullPath(),
                    $m->tracking_mode,
                    $m->stockQuantity(),
                    $m->minimum_qty,
                    $m->status?->label(),
                ], ';');
            }
        });
    }

    public function events(): StreamedResponse
    {
        $rows = Event::query()
            ->with(['vehicle:id,name', 'material:id,name', 'assignee:id,name'])
            ->orderByDesc('created_at')
            ->get();

        return $this->stream('evenements.csv', ['Titre', 'Type', 'Statut', 'Priorité', 'Véhicule', 'Matériel', 'Assigné', 'Créé le'], function ($out) use ($rows) {
            foreach ($rows as $e) {
                fputcsv($out, [
                    $e->title,
                    $e->type->label(),
                    $e->status->label(),
                    $e->priority,
                    $e->vehicle?->name,
                    $e->material?->name,
                    $e->assignee?->name,
                    $e->created_at?->format('d/m/Y H:i'),
                ], ';');
            }
        });
    }

    private function stream(string $filename, array $header, callable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($header, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM UTF-8
            fputcsv($out, $header, ';');
            $rows($out);
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
