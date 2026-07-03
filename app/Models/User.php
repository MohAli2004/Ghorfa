<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Review;
use App\Models\LandlordApplication;
use App\Models\Notification;
use App\Traits\HasAdminRole;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, HasAdminRole;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'password',
        'role',
        'phone_nb',
        'profile_image',
        'date_of_birth',
        'address',
        'last_login_at'
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
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'phone_nb' => 'string',
        'role' => 'string',
        'date_of_birth' => 'date',
        'last_login_at' => 'datetime',
    ];

    public function properties()
    {
        return $this->hasMany(Property::class);
    }

    /**
     * Get the properties that the user has liked
     */
    public function likedProperties()
    {
        return $this->belongsToMany(Property::class, 'property_likes')
                    ->withTimestamps();
    }

    /**
     * Get the user's full name.
     */
    public function getFullNameAttribute()
    {
        return $this->first_name . ' ' . $this->last_name;
    }

    /**
     * Get the user's full name.
     */
    public function getNameAttribute()
    {
        return $this->getFullNameAttribute();
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function propertyReviews()
    {
        return $this->hasManyThrough(
            Review::class,     
            Property::class,   
            'user_id',                    
            'property_id',                 
            'id',                         
            'id'                        
        );
    }

    public function landlordApplication()
    {
        return $this->hasOne(LandlordApplication::class);
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function propertyInteractions()
    {
        return $this->hasMany(PropertyInteraction::class);
    }

    public function propertySearches()
    {
        return $this->hasMany(PropertySearch::class);
    }

    public function isLandlord(): bool
    {
        return $this->role === 'landlord' || $this->isAdmin();
    }

    public function isClient(): bool
    {
        return $this->role === 'client';
    }

    public function canBecomeLandlord(): bool
    {
        return $this->isClient() && !$this->hasPendingLandlordApplication();
    }

    public function hasPendingLandlordApplication(): bool
    {
        return $this->landlordApplication()
            ->where('status', 'pending')
            ->exists();
    }
}
