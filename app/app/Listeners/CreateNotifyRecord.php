<?php
/**
 * Created by PhpStorm.
 * User: tungnt
 * Date: 10/22/19
 * Time: 23:54
 */

namespace OneSite\Notify\Listeners;

use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use OneSite\Notify\Models\Notification;
use OneSite\Notify\Models\NotificationDevice;
use OneSite\Notify\Models\NotificationRecord;
use OneSite\Notify\Services\Common\Notify;


/**
 * Class CreateNotifyRecord
 * @package OneSite\Notify\Listeners
 */
class CreateNotifyRecord
{

    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $log;

    /**
     * CreateNotifyRecord constructor.
     */
    public function __construct()
    {
        $this->log = Log::channel('notification');;
    }

    /**
     * @param \OneSite\Notify\Events\CreateNotifyRecord $event
     */
    public function handle(\OneSite\Notify\Events\CreateNotifyRecord $event)
    {
        $notification = $event->getNotification();

        switch ($notification->receiver_type) {
            case Notify::RECEIVER_TYPE_ALL:
                $this->createRecordsByAll($notification);

                break;
            case Notify::RECEIVER_TYPE_USER:
                $this->createRecordsByUser($notification);

                break;
        }
    }

    /**
     * @param Notification $notification
     */
    private function createRecordsByAll(Notification $notification)
    {
        $this->log->info('Create notify admin records:', [
            'notification' => $notification
        ]);

        $query = "INSERT INTO notification_records (notification_id, device_id, user_id, `status`, is_read, created_at, updated_at)
	                (SELECT :notification_id, nd.id, nd.user_id, 'PENDING', 0, NOW(), NOW() FROM notification_devices AS nd ORDER BY created_at DESC)";

        DB::insert($query, [
            'notification_id' => $notification->id
        ]);

        $notificationRecords = NotificationRecord::query()
            ->where('notification_id', $notification->id)
            ->where('status', Notify::STATUS_RECORD_PENDING)
            ->get();
        foreach ($notificationRecords as $notificationRecord) {
            event(new \OneSite\Notify\Events\SendNotifyRecord($notificationRecord));
        }
    }

    /**
     * @param Notification $notification
     */
    private function createRecordsByUser(Notification $notification)
    {
        $this->log->info('Create notify member records:', [
            'notification' => $notification
        ]);

        $notificationDevice = $this->getNotificationDevice($notification->receiver_id);

        if (!$notificationDevice instanceof NotificationDevice) {
            $this->log->warning('Create notify member records: not found device token', [
                'notification' => $notification
            ]);

            return;
        }

        $notificationRecord = NotificationRecord::query()->create([
            'notification_id' => $notification->id,
            'device_id' => $notificationDevice->id,
            'user_id' => $notificationDevice->user_id,
            'status' => Notify::STATUS_RECORD_PENDING,
            'is_read' => 0
        ]);

        event(new \OneSite\Notify\Events\SendNotifyRecord($notificationRecord));
    }

    /**
     * @param $userId
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Model|object|null
     */
    private function getNotificationDevice($userId)
    {
        return NotificationDevice::query()
            ->where('user_id', $userId)
            ->orderBy('id', 'desc')
            ->first();
    }
}