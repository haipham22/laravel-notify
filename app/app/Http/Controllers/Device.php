<?php
/**
 * Created by PhpStorm.
 * User: tungnt
 * Date: 10/22/19
 * Time: 22:32
 */

namespace OneSite\Notify\Http\Controllers;


use OneSite\Notify\Http\Requests\StoreDeviceRequest;
use OneSite\Notify\Models\NotificationDevice;


/**
 * Class Device
 * @package OneSite\Notify\Http\Controllers
 */
class Device extends Base
{


    /**
     * @param StoreDeviceRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(StoreDeviceRequest $request)
    {
        $user = $request->user();

        if (empty($user)) {
            return response()->json([
                'success' => false,
                'error' => [
                    'message' => 'User not login.'
                ]
            ]);
        }

        $device = NotificationDevice::query()
            ->where('user_id', $user->id)
            ->where('token', $request->token)
            ->first();
        if (!$device instanceof NotificationDevice) {
            NotificationDevice::query()
                ->where('user_id', $user->id)
                ->orWhere('token', $request->token)
                ->delete();

            NotificationDevice::query()->create([
                'user_id' => $user->id,
                'token' => $request->token
            ]);
        }

        return response()->json([]);
    }

}