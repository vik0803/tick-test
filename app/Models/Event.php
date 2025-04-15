<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Event extends Model
{
    use HasFactory;

    protected $guarded = [];
    protected $primaryKey = 'event_id';
    public $timestamps = true;

    protected $fillable = [
        'event_name',
        'event_date',
        'event_time',
        'location',
        'ticket_prefix'
    ];

    protected $casts = [
        'event_date' => 'date',
        'event_time' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    public function invitations()
    {
        return $this->hasMany(Invitation::class, 'event_id');
    }

    public function getAll($searchTerm)
    {
        return $this->where(function ($query) use ($searchTerm) {
                $query->where('event_name', 'like', '%' . $searchTerm . '%')
                      ->orWhere('location', 'like', '%' . $searchTerm . '%');
            })
            ->latest()
            ->paginate(10);
    }

    public function getRow($eventId)
    {
        return $this->find($eventId);
    }
}
