<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Concerns\BelongsToOrganisation;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

// organisation_id et is_active ne sont jamais renseignés en mass assignment public.
#[Fillable(['first_name', 'last_name', 'name', 'username', 'grade', 'email', 'password', 'avatar_path'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use BelongsToOrganisation, HasFactory, HasRoles, Notifiable, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'is_active' => 'boolean',
            'password' => 'hashed',
        ];
    }

    /** Véhicules sur lesquels l'utilisateur est autorisé. */
    public function vehicles(): BelongsToMany
    {
        return $this->belongsToMany(Vehicle::class, 'vehicle_user');
    }

    /** Sites auxquels l'utilisateur est rattaché (vide = tous les sites). */
    public function sites(): BelongsToMany
    {
        return $this->belongsToMany(Site::class, 'site_user');
    }

    /**
     * Ids des sites accessibles. `null` = aucun cloisonnement (voit tout) :
     * un utilisateur sans rattachement de site accède à toute l'organisation.
     *
     * @return list<int>|null
     */
    public function accessibleSiteIds(): ?array
    {
        $ids = $this->sites()->pluck('sites.id')->all();

        return $ids === [] ? null : $ids;
    }

    public function isActive(): bool
    {
        return (bool) $this->is_active;
    }

    /** Nom complet d'affichage (prénom + nom). */
    public function fullName(): string
    {
        return trim("{$this->first_name} {$this->last_name}") ?: $this->name;
    }
}
