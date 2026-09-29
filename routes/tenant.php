<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\BagController;
use App\Http\Controllers\DisinfectionController;
use App\Http\Controllers\DisinfectionProtocolController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\LeaveController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\MaintenanceController;
use App\Http\Controllers\MaterialCategoryController;
use App\Http\Controllers\MaterialController;
use App\Http\Controllers\MaterialItemController;
use App\Http\Controllers\MaterialTypeController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PharmacyController;
use App\Http\Controllers\ProtocolController;
use App\Http\Controllers\ProtocolTemplateController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SiteController;
use App\Http\Controllers\StockLotController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\BodyDamageController;
use App\Http\Controllers\FuelController;
use App\Http\Controllers\ServiceProtocolController;
use App\Http\Controllers\ServiceSessionController;
use App\Http\Controllers\TerrainController;
use App\Http\Controllers\VehicleTaskController;
use App\Http\Controllers\VehicleController;
use App\Http\Controllers\VehicleModelController;
use App\Http\Controllers\VehicleTypeController;
use Illuminate\Support\Facades\Route;

// Routes métier d'une organisation (sous-domaine résolu + authentification).
Route::middleware(['auth', 'tenant'])->group(function () {

    // Site actif (filtre d'affichage) — accessible à tous les utilisateurs.
    Route::post('site-switch', [SiteController::class, 'switch'])->name('sites.switch');

    // Recherche globale (résultats filtrés par permissions dans le contrôleur).
    Route::get('search', [SearchController::class, 'index'])->name('search.index');
    Route::get('search/suggest', [SearchController::class, 'suggest'])->name('search.suggest');

    // Enregistrement d'un plein de carburant : accessible à tout agent connecté
    // (le personnel de terrain fait le plein). Suppression réservée (voir ci-dessus).
    Route::post('vehicles/{vehicle}/fuel', [FuelController::class, 'store'])->name('vehicles.fuel.store');

    // Réalisation d'une tâche véhicule : accessible à tout agent connecté (terrain).
    Route::post('vehicles/{vehicle}/tasks/{task}/complete', [VehicleTaskController::class, 'complete'])->name('vehicles.tasks.complete');

    // État carrosserie : pointage d'anomalies (accès contrôlé dans le contrôleur :
    // service en cours sur le véhicule, ou gestionnaire).
    Route::post('vehicles/{vehicle}/body-damages', [BodyDamageController::class, 'store'])->name('vehicles.body-damages.store');
    Route::post('vehicles/{vehicle}/body-damages/{bodyDamage}/resolve', [BodyDamageController::class, 'resolve'])->name('vehicles.body-damages.resolve');
    Route::get('vehicles/{vehicle}/body-damages/{bodyDamage}/photo', [BodyDamageController::class, 'photo'])->name('vehicles.body-damages.photo');

    // Schéma de carrosserie d'un modèle (image servie depuis le disque privé),
    // visible par tout agent (affichage du contrôle carrosserie).
    Route::get('vehicle-models/{vehicleModel}/schematic/{view}', [VehicleModelController::class, 'schematic'])->name('vehicle-models.schematic');

    // Documents : consultation (motif journalisé) + service du fichier + consentement.
    // Le contrôle d'accès est fait dans le contrôleur (service en cours / titulaire / admin).
    Route::post('documents/{document}/consult', [DocumentController::class, 'consult'])->name('documents.consult');
    Route::get('documents/{document}/file', [DocumentController::class, 'file'])->name('documents.file');
    Route::post('documents/consent', [DocumentController::class, 'consent'])->name('documents.consent');

    // Application terrain (PWA mobile salariés). Accessible à tout utilisateur
    // connecté ; les actions (désinfection, entretien, anomalie) restent soumises
    // à leurs permissions respectives.
    Route::prefix('t')->name('terrain.')->group(function () {
        Route::get('/', [TerrainController::class, 'home'])->name('home');
        Route::get('anomalie', [TerrainController::class, 'anomalyForm'])->name('anomaly');
        Route::post('anomalie', [TerrainController::class, 'reportAnomaly'])->middleware('permission:anomalies.manage')->name('anomaly.store');
        Route::get('anomalie/{event}/photo', [TerrainController::class, 'anomalyPhoto'])->name('anomaly.photo');
        Route::get('scanner', [TerrainController::class, 'scan'])->name('scan');
        Route::get('mes-evenements', [TerrainController::class, 'myEvents'])->name('events');
        Route::get('conges', [LeaveController::class, 'terrain'])->name('leave');
        Route::get('vehicules/{vehicle}/prise-de-service', [TerrainController::class, 'serviceStart'])->name('service-start');
        Route::post('vehicules/{vehicle}/prise-de-service', [TerrainController::class, 'openSession'])->name('service-start.store');
        Route::get('vehicules/{vehicle}/fin-de-service', [TerrainController::class, 'serviceEnd'])->name('service-end');
        Route::post('vehicules/{vehicle}/fin-de-service', [TerrainController::class, 'closeSession'])->name('service-end.store');
        Route::post('vehicules/{vehicle}/binome', [TerrainController::class, 'changePartner'])->name('partner');
        Route::get('vehicules/{vehicle}', [TerrainController::class, 'vehicle'])->name('vehicle');
    });

    // Notifications in-app.
    Route::post('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::post('notifications/{id}/read', [NotificationController::class, 'read'])->name('notifications.read');

    // Exports CSV.
    Route::middleware('permission:exports.create')->group(function () {
        Route::get('exports/materiel.csv', [ExportController::class, 'materials'])->name('exports.materials');
        Route::get('exports/evenements.csv', [ExportController::class, 'events'])->name('exports.events');
    });

    // Administration des utilisateurs.
    Route::middleware('permission:users.manage')->group(function () {
        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::post('users/invite', [UserController::class, 'store'])->name('users.invite');
        Route::patch('users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::post('users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('users.reset-password');
        Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
        Route::delete('invitations/{invitation}', [UserController::class, 'cancelInvitation'])->name('invitations.cancel');
        Route::post('invitations/{invitation}/resend', [UserController::class, 'resendInvitation'])->name('invitations.resend');
    });

    // Sites (centres / dépôts).
    Route::middleware('permission:sites.manage')->group(function () {
        Route::get('sites', [SiteController::class, 'index'])->name('sites.index');
        Route::post('sites', [SiteController::class, 'store'])->name('sites.store');
        Route::patch('sites/{site}', [SiteController::class, 'update'])->name('sites.update');
        Route::delete('sites/{site}', [SiteController::class, 'destroy'])->name('sites.destroy');
    });

    // Véhicules + affectations.
    Route::middleware('permission:vehicles.manage')->group(function () {
        Route::get('vehicles', [VehicleController::class, 'index'])->name('vehicles.index');
        Route::get('vehicles/{vehicle}', [VehicleController::class, 'show'])->name('vehicles.show');
        Route::post('vehicles', [VehicleController::class, 'store'])->name('vehicles.store');
        Route::patch('vehicles/{vehicle}', [VehicleController::class, 'update'])->name('vehicles.update');
        Route::delete('vehicles/{vehicle}', [VehicleController::class, 'destroy'])->name('vehicles.destroy');
        Route::put('vehicles/{vehicle}/assignments', [VehicleController::class, 'assignments'])->name('vehicles.assignments');
        Route::put('vehicles/{vehicle}/disinfection-protocols', [VehicleController::class, 'disinfectionProtocols'])->name('vehicles.disinfection-protocols');
        // Applique le modèle/motorisation affecté : génère emplacements + entretien.
        Route::post('vehicles/{vehicle}/apply-model', [VehicleController::class, 'applyModel'])->name('vehicles.apply-model');

        // Suivi de service : services en cours + historique des prises/fins de service.
        Route::get('suivi-service', [ServiceSessionController::class, 'index'])->name('service-sessions.index');

        // Suivi mécanique.
        Route::post('vehicles/{vehicle}/mileage', [MaintenanceController::class, 'updateMileage'])->name('vehicles.mileage');
        Route::post('vehicles/{vehicle}/maintenances', [MaintenanceController::class, 'store'])->name('vehicles.maintenances.store');
        Route::delete('vehicles/{vehicle}/maintenances/{maintenance}', [MaintenanceController::class, 'destroy'])->name('vehicles.maintenances.destroy');
        // Suppression d'un plein (correction) : réservée aux gestionnaires.
        Route::delete('vehicles/{vehicle}/fuel/{fuel}', [FuelController::class, 'destroy'])->name('vehicles.fuel.destroy');
        // Suppression d'une anomalie carrosserie (correction) : réservée aux gestionnaires.
        Route::delete('vehicles/{vehicle}/body-damages/{bodyDamage}', [BodyDamageController::class, 'destroy'])->name('vehicles.body-damages.destroy');

        // Documents (agrément, CT, carte grise, diplômes, ARS, permis…) : admins seulement.
        Route::middleware('permission:documents.manage')->group(function () {
            Route::post('documents', [DocumentController::class, 'store'])->name('documents.store');
            Route::delete('documents/{document}', [DocumentController::class, 'destroy'])->name('documents.destroy');
        });

        // Tâches véhicule : création / gestion par les responsables.
        Route::post('vehicles/{vehicle}/tasks', [VehicleTaskController::class, 'store'])->name('vehicles.tasks.store');
        Route::post('vehicles/{vehicle}/tasks/{task}/reopen', [VehicleTaskController::class, 'reopen'])->name('vehicles.tasks.reopen');
        Route::delete('vehicles/{vehicle}/tasks/{task}', [VehicleTaskController::class, 'destroy'])->name('vehicles.tasks.destroy');

        // Catalogue des types de véhicule (VSAV, Ambulance type A…).
        Route::get('vehicle-types', [VehicleTypeController::class, 'index'])->name('vehicle-types.index');
        Route::post('vehicle-types', [VehicleTypeController::class, 'store'])->name('vehicle-types.store');
        Route::patch('vehicle-types/{vehicleType}', [VehicleTypeController::class, 'update'])->name('vehicle-types.update');
        Route::post('vehicle-types/{vehicleType}/toggle', [VehicleTypeController::class, 'toggle'])->name('vehicle-types.toggle');
        Route::delete('vehicle-types/{vehicleType}', [VehicleTypeController::class, 'destroy'])->name('vehicle-types.destroy');

        // Protocoles de service (ouverture / fermeture) par type de véhicule.
        Route::get('protocoles-service', [ServiceProtocolController::class, 'index'])->name('service-protocols.index');
        Route::post('protocoles-service', [ServiceProtocolController::class, 'save'])->name('service-protocols.save');

        // Modèles de véhicule : gabarits d'emplacements générés à la création d'un véhicule.
        Route::get('vehicle-models', [VehicleModelController::class, 'index'])->name('vehicle-models.index');
        Route::post('vehicle-models', [VehicleModelController::class, 'store'])->name('vehicle-models.store');
        Route::patch('vehicle-models/{vehicleModel}', [VehicleModelController::class, 'update'])->name('vehicle-models.update');
        Route::post('vehicle-models/{vehicleModel}/toggle', [VehicleModelController::class, 'toggle'])->name('vehicle-models.toggle');
        Route::delete('vehicle-models/{vehicleModel}', [VehicleModelController::class, 'destroy'])->name('vehicle-models.destroy');
        // Schémas de carrosserie du modèle (par vue).
        Route::post('vehicle-models/{vehicleModel}/schematic', [VehicleModelController::class, 'uploadSchematic'])->name('vehicle-models.schematic.upload');
        Route::delete('vehicle-models/{vehicleModel}/schematic/{view}', [VehicleModelController::class, 'deleteSchematic'])->name('vehicle-models.schematic.delete');
    });

    // Traçabilité des désinfections / nettoyages (accessible aux opérateurs habilités).
    Route::middleware('permission:disinfections.record')->group(function () {
        Route::post('vehicles/{vehicle}/disinfections', [DisinfectionController::class, 'store'])->name('vehicles.disinfections.store');
        Route::delete('vehicles/{vehicle}/disinfections/{disinfection}', [DisinfectionController::class, 'destroy'])->name('vehicles.disinfections.destroy');
    });

    // Bibliothèque de protocoles de désinfection (ambulance privée — vérifié dans le contrôleur).
    Route::middleware('permission:vehicles.manage')->group(function () {
        Route::get('disinfection-protocols', [DisinfectionProtocolController::class, 'index'])->name('disinfection-protocols.index');
        Route::post('disinfection-protocols', [DisinfectionProtocolController::class, 'store'])->name('disinfection-protocols.store');
        Route::patch('disinfection-protocols/{disinfectionProtocol}', [DisinfectionProtocolController::class, 'update'])->name('disinfection-protocols.update');
        Route::post('disinfection-protocols/{disinfectionProtocol}/toggle', [DisinfectionProtocolController::class, 'toggle'])->name('disinfection-protocols.toggle');
        Route::delete('disinfection-protocols/{disinfectionProtocol}', [DisinfectionProtocolController::class, 'destroy'])->name('disinfection-protocols.destroy');
    });

    // RH : congés & absences. Toute personne connectée gère ses propres demandes ;
    // la validation est réservée aux responsables (permission leave.manage).
    Route::get('leave', [LeaveController::class, 'index'])->name('leave.index');
    Route::post('leave', [LeaveController::class, 'store'])->name('leave.store');
    Route::patch('leave/{leaveRequest}', [LeaveController::class, 'update'])->name('leave.update');
    Route::post('leave/{leaveRequest}/cancel', [LeaveController::class, 'cancel'])->name('leave.cancel');
    Route::post('leave/{leaveRequest}/decision', [LeaveController::class, 'decide'])
        ->middleware('permission:leave.manage')->name('leave.decide');
    Route::post('leave/rules', [LeaveController::class, 'saveRules'])
        ->middleware('permission:leave.manage')->name('leave.rules');

    // Historique des actions (journal d'activité).
    Route::middleware('permission:history.view')->group(function () {
        Route::get('activity', [ActivityController::class, 'index'])->name('activity.index');
    });

    // Pharmacie : déclaration rapide des consommables.
    Route::middleware('permission:pharmacy.manage')->group(function () {
        Route::get('pharmacy', [PharmacyController::class, 'index'])->name('pharmacy.index');
        Route::post('pharmacy/consumables', [PharmacyController::class, 'store'])->name('pharmacy.consumables.store');
    });

    // Événements (anomalies / réparations) — tableau Kanban.
    Route::middleware('permission:anomalies.manage')->group(function () {
        Route::get('events', [EventController::class, 'index'])->name('events.index');
        Route::post('events', [EventController::class, 'store'])->name('events.store');
        Route::patch('events/{event}', [EventController::class, 'update'])->name('events.update');
        Route::patch('events/{event}/move', [EventController::class, 'move'])->name('events.move');
        Route::post('events/{event}/comments', [EventController::class, 'comment'])->name('events.comment');
        Route::patch('events/{event}/material-status', [EventController::class, 'materialStatus'])->name('events.material-status');
        Route::delete('events/{event}', [EventController::class, 'destroy'])->name('events.destroy');

        // Tableaux et colonnes Kanban (personnalisation).
        Route::post('kanban/boards', [EventController::class, 'storeBoard'])->name('events.boards.store');
        Route::delete('kanban/boards/{board}', [EventController::class, 'destroyBoard'])->name('events.boards.destroy');
        Route::post('kanban/boards/{board}/columns', [EventController::class, 'storeColumn'])->name('events.columns.store');
        Route::patch('kanban/columns/{column}', [EventController::class, 'updateColumn'])->name('events.columns.update');
        Route::delete('kanban/columns/{column}', [EventController::class, 'destroyColumn'])->name('events.columns.destroy');
    });

    // Réglages de l'organisation (propriétaire / administrateur).
    Route::middleware('permission:settings.manage')->group(function () {
        Route::get('settings', [SettingsController::class, 'index'])->name('settings.index');
        Route::patch('settings', [SettingsController::class, 'update'])->name('settings.update');
    });

    // Modèles de protocole.
    Route::middleware('permission:templates.manage')->group(function () {
        Route::get('templates', [ProtocolTemplateController::class, 'index'])->name('templates.index');
        Route::post('templates', [ProtocolTemplateController::class, 'store'])->name('templates.store');
        Route::get('templates/{template}/edit', [ProtocolTemplateController::class, 'edit'])->name('templates.edit');
        Route::patch('templates/{template}', [ProtocolTemplateController::class, 'update'])->name('templates.update');
        Route::patch('templates/{template}/exclusions', [ProtocolTemplateController::class, 'exclusions'])->name('templates.exclusions');
        Route::delete('templates/{template}', [ProtocolTemplateController::class, 'destroy'])->name('templates.destroy');
    });

    // Réalisation des protocoles (vérificateur ou gestionnaire).
    Route::middleware('permission:protocols.perform|protocols.manage')->group(function () {
        Route::get('protocols', [ProtocolController::class, 'index'])->name('protocols.index');
        Route::post('protocols', [ProtocolController::class, 'start'])->name('protocols.start');
        Route::get('protocols/{protocol}', [ProtocolController::class, 'show'])->name('protocols.show');
        Route::get('protocols/{protocol}/report', [ProtocolController::class, 'report'])->name('protocols.report');
        Route::patch('protocols/{protocol}/items/{item}', [ProtocolController::class, 'updateItem'])->name('protocols.items.update');
        Route::post('protocols/{protocol}/validate', [ProtocolController::class, 'finalize'])->name('protocols.validate');
    });

    // Emplacements & sous-emplacements.
    Route::middleware('permission:locations.manage')->group(function () {
        Route::get('locations', [LocationController::class, 'index'])->name('locations.index');
        Route::post('locations', [LocationController::class, 'store'])->name('locations.store');
        Route::patch('locations/{location}', [LocationController::class, 'update'])->name('locations.update');
        Route::post('locations/{location}/toggle', [LocationController::class, 'toggle'])->name('locations.toggle');
        Route::delete('locations/{location}', [LocationController::class, 'destroy'])->name('locations.destroy');

        // Espace dédié aux sacs (création, transferts entre véhicules).
        Route::get('sacs', [BagController::class, 'index'])->name('bags.index');
        Route::post('sacs', [BagController::class, 'store'])->name('bags.store');
        Route::post('sacs/{location}/transfer', [BagController::class, 'transfer'])->name('bags.transfer');
    });

    // Catalogue matériel + catégories.
    Route::middleware('permission:catalog.manage')->group(function () {
        Route::get('materials', [MaterialController::class, 'index'])->name('materials.index');
        Route::get('materials/{material}', [MaterialController::class, 'show'])->name('materials.show');
        Route::post('materials', [MaterialController::class, 'store'])->name('materials.store');
        Route::patch('materials/{material}', [MaterialController::class, 'update'])->name('materials.update');
        Route::patch('materials/{material}/status', [MaterialController::class, 'quickStatus'])->name('materials.status');
        Route::patch('materials/{material}/stock', [MaterialController::class, 'setStock'])->name('materials.stock');
        Route::delete('materials/{material}', [MaterialController::class, 'destroy'])->name('materials.destroy');

        // Exemplaires (mode unitaire) et lots (mode consommable).
        Route::post('materials/{material}/items', [MaterialItemController::class, 'store'])->name('material-items.store');
        Route::patch('material-items/{item}', [MaterialItemController::class, 'update'])->name('material-items.update');
        Route::delete('material-items/{item}', [MaterialItemController::class, 'destroy'])->name('material-items.destroy');

        Route::post('materials/{material}/lots', [StockLotController::class, 'store'])->name('stock-lots.store');
        Route::patch('stock-lots/{lot}', [StockLotController::class, 'update'])->name('stock-lots.update');
        Route::delete('stock-lots/{lot}', [StockLotController::class, 'destroy'])->name('stock-lots.destroy');

        Route::get('material-categories', [MaterialCategoryController::class, 'index'])->name('material-categories.index');
        Route::post('material-categories', [MaterialCategoryController::class, 'store'])->name('material-categories.store');
        Route::delete('material-categories/{category}', [MaterialCategoryController::class, 'destroy'])->name('material-categories.destroy');

        // Catalogue des types de matériel (Thermomètre, Compresse 5×5…).
        Route::get('material-types', [MaterialTypeController::class, 'index'])->name('material-types.index');
        Route::post('material-types', [MaterialTypeController::class, 'store'])->name('material-types.store');
        Route::patch('material-types/{materialType}', [MaterialTypeController::class, 'update'])->name('material-types.update');
        Route::post('material-types/{materialType}/toggle', [MaterialTypeController::class, 'toggle'])->name('material-types.toggle');
        Route::delete('material-types/{materialType}', [MaterialTypeController::class, 'destroy'])->name('material-types.destroy');
    });
});
