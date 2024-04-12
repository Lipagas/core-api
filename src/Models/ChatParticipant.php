<?php

namespace Fleetbase\Models;

use Fleetbase\Traits\HasApiModelBehavior;
use Fleetbase\Traits\HasPublicId;
use Fleetbase\Traits\HasUuid;
use Fleetbase\Traits\SendsWebhooks;

class ChatParticipant extends Model
{
    use HasUuid;
    use HasPublicId;
    use HasApiModelBehavior;
    use SendsWebhooks;

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'chat_participants';

    /**
     * The type of public Id to generate.
     *
     * @var string
     */
    protected $publicIdType = 'chat_participant';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['company_uuid', 'chat_channel_uuid', 'user_uuid'];

    /**
     * Dynamic attributes that are appended to object.
     *
     * @var array
     */
    protected $appends = ['is_online', 'last_seen_at'];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_uuid', 'uuid');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function chatChannel()
    {
        return $this->belongsTo(ChatChannel::class, 'chat_channel_uuid', 'uuid');
    }

    /**
     * Retrieves the current chat participant based on the active session user.
     *
     * This static method queries and returns the ChatParticipant instance corresponding
     * to the user currently stored in the session. It uses the 'user_uuid' stored in the
     * session to find the matching participant. If no matching participant is found,
     * or if the session does not have a 'user' set, the method returns null.
     *
     * @return ChatParticipant|null the ChatParticipant instance for the current session user, or null if not found
     */
    public static function current(string $chatChannelId): ?ChatParticipant
    {
        return static::where(['user_uuid' => session('user'), 'chat_channel_uuid' => $chatChannelId])->first();
    }

    public function getLastSeenAtAttribute()
    {
        return $this->user ? $this->user->lastSeenAt() : null;
    }

    public function getIsOnlineAttribute()
    {
        return $this->user ? $this->user->isOnline() : null;
    }
}
