<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

use App\Models\Concerns\BelongsToCompany;
class ChatConversation extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'guest_token',
        'client_id',
        'visitor_name',
        'visitor_email',
        'tracking_code',
        'status',
        'assigned_user_id',
        'last_message_at',
        'unread_staff_count',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
        'unread_staff_count' => 'integer',
    ];

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'conversation_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }
}
