<?php

namespace CouponCommissionManager\Services;

use CouponCommissionManager\Models\CommissionLog;

class PaymentService {

    public static function mark_paid( int $log_id, string $note = '' ): bool {
        return CommissionLog::mark_paid( $log_id, get_current_user_id(), $note );
    }

    public static function mark_void( int $log_id ): bool {
        return CommissionLog::mark_void( $log_id );
    }

    public static function batch_mark_paid( array $log_ids, string $note = '' ): int {
        return CommissionLog::batch_mark_paid( $log_ids, get_current_user_id(), $note );
    }
}
