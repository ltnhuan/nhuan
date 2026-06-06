<?php

namespace App\Services;

use App\Models\IntegrationEvent;
use App\Models\IntegrationSystem;
use Illuminate\Http\Request;

class InboundWebhookService
{
    public const EVENT_KEYS = ['sis.student.created','sis.student.updated','sis.student.status_changed','sis.teacher.updated','sis.class.created','sis.class.updated','sis.course_section.created','sis.enrollment.created','sis.enrollment.removed','sis.teacher.assigned','lms.progress.updated','lms.course.completed','lms.quiz.submitted','lms.assignment.graded','lms.attendance.updated','lms.grade.ready_for_review','lms.grade.approved','lms.grade.locked','lms.grade.synced','lms.certificate.issued'];

    public function verifySignature(IntegrationSystem $system, string $body, ?string $signature): bool
    {
        $secret = $system->settings['webhook_secret'] ?? null;
        if (! $secret || ! $signature) return false;
        return hash_equals(hash_hmac('sha256', $body, $secret), str_replace('sha256=', '', $signature));
    }

    public function receive(IntegrationSystem $system, Request $request): IntegrationEvent
    {
        $body = $request->getContent();
        if (! $this->verifySignature($system, $body, $request->header('X-EraLMS-Signature'))) throw new \RuntimeException('Webhook signature không hợp lệ.');
        $payload = json_decode($body, true) ?: [];
        $eventKey = $payload['event_key'] ?? $request->header('X-Event-Key') ?? 'sis.student.updated';
        $idempotencyKey = $payload['idempotency_key'] ?? $request->header('Idempotency-Key') ?? hash('sha256', $body);
        $event = IntegrationEvent::query()->where(['system_id'=>$system->id,'event_key'=>$eventKey,'idempotency_key'=>$idempotencyKey])->first();
        if ($event) {
            $event->forceFill(['status' => 'ignored'])->save();
            return $event->fresh();
        }
        $event = IntegrationEvent::query()->create(['tenant_id'=>$system->tenant_id,'system_id'=>$system->id,'event_key'=>$eventKey,'direction'=>'inbound','entity_type'=>$payload['entity_type'] ?? $this->entityFromEvent($eventKey),'entity_id'=>$payload['entity_id'] ?? null,'idempotency_key'=>$idempotencyKey,'payload'=>$payload,'status'=>'pending']);
        return $this->process($event);
    }

    public function process(IntegrationEvent $event): IntegrationEvent
    {
        $event->forceFill(['status'=>'processing','attempts'=>$event->attempts + 1])->save();
        $event->forceFill(['status'=>'success','processed_at'=>now()])->save();
        return $event->fresh();
    }

    private function entityFromEvent(string $eventKey): string
    {
        return explode('.', $eventKey)[1] ?? 'unknown';
    }
}
