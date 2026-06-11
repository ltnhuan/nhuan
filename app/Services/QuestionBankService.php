<?php

namespace App\Services;

use App\Models\QuestionBank;

class QuestionBankService
{
    public function createBank(array $data): QuestionBank
    {
        return QuestionBank::query()->create($data + ['status' => 'draft', 'visibility' => 'private']);
    }

    public function updateBank(QuestionBank $bank, array $data): QuestionBank
    {
        $bank->fill($data)->save();
        return $bank->fresh();
    }

    public function archiveBank(QuestionBank $bank): QuestionBank
    {
        $bank->forceFill(['status' => 'archived'])->save();
        return $bank;
    }

    public function cloneBank(QuestionBank $bank, int $ownerId): QuestionBank
    {
        $copy = $bank->replicate(['code', 'status', 'owner_id']);
        $copy->code = $bank->code.'-COPY-'.now()->format('His');
        $copy->name = $bank->name.' - Bản sao';
        $copy->status = 'draft';
        $copy->owner_id = $ownerId;
        $copy->save();
        return $copy;
    }

    public function submitReview(QuestionBank $bank): QuestionBank
    {
        $bank->forceFill(['status' => 'review'])->save();
        return $bank;
    }

    public function approveBank(QuestionBank $bank): QuestionBank
    {
        $bank->forceFill(['status' => 'approved'])->save();
        return $bank;
    }
}
