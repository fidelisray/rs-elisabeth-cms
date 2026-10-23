<?php

namespace App\Http\Resources\Api\Cms;

use App\Models\Doctor;
use Illuminate\Http\Request;

/**
 * @mixin Doctor
 */
class DoctorResource extends BaseCmsResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'paramedic_code' => $this->paramedic_code,
            'name' => $this->name,
            'specialty_code' => $this->specialty_code,
            'specialty_name' => $this->specialty_name,
            'service_unit_name' => $this->service_unit_name,
            'bio' => $this->bio,
            'location' => $this->location,
            'experience' => $this->experience,
            'education' => $this->education,
            'is_profile_complete' => $this->is_profile_complete,
            'is_active' => $this->is_active,
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
