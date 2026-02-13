<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use App\Models\UserLocationDepartmentRole;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class User extends Authenticatable implements JWTSubject
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'username',
        'dial_code',
        'phone',
        'address',
        'signature',
        'is_super_admin',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
    
    public function assignments()
    {
        return $this->hasMany(UserLocationDepartmentRole::class);
    }

    public function userAssignments()
    {
        return $this->hasMany(UserAssignment::class);
    }

     public function uldr()
    {
        return $this->hasMany(UserLocationDepartmentRole::class);
    }

    public function customPermissions()
    {
        return $this->hasMany(UserCustomPermission::class, 'user_location_department_role_id', 'id');
    }

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT
     */
    public function getJWTCustomClaims()
    {
        return [];
    }

    public static function createLabUser($name,$email)
    {
        return self::firstOrCreate(
            ['email'=>$email],
            [
                'name'=>$name,
                'username'=>strtolower($name).'_'.explode('@',$email)[0],
                'password'=> Hash::make('defaultpassword123'),
                'is_super_admin'=>true
            ]
        );
    }

    public function labs()
    {
        return $this->belongsToMany(Lab::class, 'lab_users');
    }

    public function labUser()
    {
        return $this->hasOne(LabUser::class, 'user_id')
                    ->with('lab');
    }

    public function userAssignmentsWithRelations()
    {
        return $this->hasMany(UserAssignment::class)->with(['lab']);
    }

    public function rolesWithLab()
    {
        return $this->belongsToMany(Role::class, 'model_has_roles', 'model_id', 'role_id')
            ->withPivot('lab_id')
            ->wherePivot('model_type', self::class)
            ->leftJoin('labs', 'labs.id', '=', 'model_has_roles.lab_id')
            ->select(
                'roles.*',
                'model_has_roles.lab_id',
                'labs.name as lab_name'
            );
    }



    
}


