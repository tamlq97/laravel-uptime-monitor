<?php

/**
 * VPS 2vCPU + 8GB RAM → giả sử chỉ muốn xử lý ~2000 monitor mỗi phút.
 * Nếu có 10k monitor: ceil(10000 / 2000) = 5 bucket.
 * Nếu có 50k monitor: ceil(50000 / 2000) = 25 bucket.
 */
return [
    'max_monitors_per_minute' => 2000, // giới hạn theo tài nguyên VPS
    'batch_size' => 100, // số monitor trong 1 job
];
