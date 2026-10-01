<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CompanySetting\UpdateCompanySettingRequest;
use App\Models\CompanySetting;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

class CompanySettingController extends Controller
{
    use AuthorizesRequests;

    public function index()
    {
        $this->authorize('viewAny', CompanySetting::class);

        $settings = CompanySetting::all()->pluck('value', 'key');

        return response()->json([
            'allowed_ips' => $settings->get('allowed_ips', []),
            'late_tolerance_minutes' => $settings->get('late_tolerance_minutes', 10),
            'absence_delay_minutes' => $settings->get('absence_delay_minutes', 60),
            'explanation_request_minutes_before' => $settings->get('explanation_request_minutes_before', 5),
            'auto_checkin_mode' => $settings->get('auto_checkin_mode', 'disabled'),
            'timezone' => $settings->get('timezone', config('app.timezone')),
        ]);
    }

    public function update(UpdateCompanySettingRequest $request)
    {
        $this->authorize('update', CompanySetting::class);

        $data = $request->validated();

        foreach ($data as $key => $value) {
            CompanySetting::updateOrCreate(['key' => $key], ['value' => $value]);
        }

        $settings = CompanySetting::all()->pluck('value', 'key');

        return response()->json([
            'allowed_ips' => $settings->get('allowed_ips', []),
            'late_tolerance_minutes' => $settings->get('late_tolerance_minutes', 10),
            'absence_delay_minutes' => $settings->get('absence_delay_minutes', 60),
            'explanation_request_minutes_before' => $settings->get('explanation_request_minutes_before', 5),
            'auto_checkin_mode' => $settings->get('auto_checkin_mode', 'disabled'),
            'timezone' => $settings->get('timezone', config('app.timezone')),
        ]);
    }
}