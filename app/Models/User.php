<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens ;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Queue\ShouldQueue ;

use Illuminate\Database\Eloquent\SoftDeletes;

class User extends Authenticatable implements CanResetPasswordContract , MustVerifyEmail , ShouldQueue
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;
    use HasApiTokens ;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'full_name',
        'email',
        'password',
        'phone',
        'image',
        'location',
        'role',
        'terms_accepted',
        'is_active'
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
            'terms_accepted' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    // علاقات الزبون (Customer)
    public function cart() { return $this->hasOne(Cart::class); }
    public function wishlist() { return $this->hasOne(Wishlist::class); }
    public function orders() { return $this->hasMany(Order::class); }
    public function reviews() { return $this->hasMany(Review::class); }

    // علاقات البائع (Seller)
    public function products() { return $this->hasMany(Product::class, 'seller_id'); }

    // علاقات عامة (إشعارات لجميع الأدوار)
    public function notifications() { return $this->hasMany(Notification::class); }

    public function adminlogs(){
        return $this->hasOne(AdminLog::class) ;
    }

      // Helper methods
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isSeller(): bool
    {
        return $this->role === 'seller';
    }

    public function isCustomer(): bool
    {
        return $this->role === 'customer';
    }


    /**
     * Send the password reset notification.
     *
     * @param  string  $token
     * @return void
     */
    public function sendPasswordResetNotification($token)
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    /**
     * Send the email verification notification.
     *
     * @return void
     */
    public function sendEmailVerificationNotification()
    {
        $this->notify(new \App\Notifications\VerifyEmailNotification());
    }

    /**
     * Users that this user has favorited.
     */
    public function favoriteUsers()
    {
        return $this->belongsToMany(User::class, 'favorites', 'user_id', 'favorite_user_id')->withTimestamps();
    }
}
