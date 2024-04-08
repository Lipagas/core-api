<?php

namespace Fleetbase\Http\Resources;

use Fleetbase\Support\Http;

class ChatLog extends FleetbaseResource
{
    /**
     * Transform the resource into an array.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'id'                                           => $this->when(Http::isInternalRequest(), $this->id),
            'uuid'                                         => $this->when(Http::isInternalRequest(), $this->uuid),
            'chat_channel_uuid'                            => $this->when(Http::isInternalRequest(), $this->chat_channel_uuid),
            'initiator_uuid'                               => $this->when(Http::isInternalRequest(), $this->initiator_uuid),
            'content'                                      => $this->content,
            'resolved_content'                             => $this->resolved_content,
            'event_type'                                   => $this->event_type,
            'status'                                       => $this->status,
            'updated_at'                                   => $this->updated_at,
            'created_at'                                   => $this->created_at,
            'deleted_at'                                   => $this->deleted_at,
        ];
    }
}
