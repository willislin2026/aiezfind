<?php
/**
 * AiezFind — WCL 加權質心定位算法
 */
require_once __DIR__ . '/db.php';

/**
 * 計算 TAG 當前位置
 *
 * @param  string $tag_id   TAG CardNo
 * @param  int    $bat      TAG 電量（%）
 * @param  int    $lastRssi 最後觀測 RSSI（dBm）
 * @return array|null       定位結果，null 表示無法定位
 */
function calculatePosition(PDO $pdo, string $tag_id, int $bat = 0, int $lastRssi = 0): ?array
{
    $txPower = (float) getSetting('tx_power',    -59);
    $n       = (float) getSetting('path_loss_n',  2.0);
    $window  = (int)   getSetting('rssi_window',  5);

    /*
     * 取時間視窗內，每個 NODE 最新的一筆 RSSI
     * 同時 JOIN nodes 表確保 NODE 已設定座標且已分配樓層
     */
    $sql = "
        SELECT rl.node_id, rl.rssi, nd.floor_id, nd.x, nd.y
        FROM rssi_log rl
        INNER JOIN (
            SELECT node_id, MAX(received_at) AS max_t
            FROM rssi_log
            WHERE tag_id = :tag
              AND received_at >= DATE_SUB(NOW(), INTERVAL :win SECOND)
            GROUP BY node_id
        ) latest
            ON rl.node_id = latest.node_id
           AND rl.received_at = latest.max_t
           AND rl.tag_id = :tag2
        INNER JOIN nodes nd
            ON nd.node_id = rl.node_id
        WHERE nd.x IS NOT NULL
          AND nd.y IS NOT NULL
          AND nd.floor_id IS NOT NULL
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':tag' => $tag_id, ':win' => $window, ':tag2' => $tag_id]);
    $nodes = $stmt->fetchAll();

    if (empty($nodes)) {
        return null;
    }

    // 只有一個 NODE → 直接使用 NODE 座標，低信心度
    if (count($nodes) === 1) {
        return [
            'floor_id'   => (int)   $nodes[0]['floor_id'],
            'x'          => (float) $nodes[0]['x'],
            'y'          => (float) $nodes[0]['y'],
            'confidence' => 0.30,
            'node_count' => 1,
        ];
    }

    // 加權質心計算
    $totalWeight  = 0.0;
    $sumX         = 0.0;
    $sumY         = 0.0;
    $floorWeights = [];

    foreach ($nodes as $nd) {
        // RSSI → 距離（公尺）
        $dist = pow(10.0, ($txPower - (float)$nd['rssi']) / (10.0 * $n));
        $dist = max(0.01, $dist);   // 避免除以零
        $w    = 1.0 / ($dist * $dist);

        $sumX        += $w * (float)$nd['x'];
        $sumY        += $w * (float)$nd['y'];
        $totalWeight += $w;

        $fid = (int)$nd['floor_id'];
        $floorWeights[$fid] = ($floorWeights[$fid] ?? 0.0) + $w;
    }

    // 選最高權重樓層
    arsort($floorWeights);
    $floorId = (int)array_key_first($floorWeights);

    // 信心度：1個NODE=0.3, 2個=0.6, 3個=0.9, 4個+=1.0
    $confidence = min(1.0, count($nodes) * 0.30);

    return [
        'floor_id'   => $floorId,
        'x'          => $sumX / $totalWeight,
        'y'          => $sumY / $totalWeight,
        'confidence' => $confidence,
        'node_count' => count($nodes),
    ];
}

/**
 * 判斷點是否在多邊形內（Ray Casting）
 * $point = [x, y]
 * $polygon = [[x1,y1],[x2,y2],...]
 */
function pointInPolygon(array $point, array $polygon): bool
{
    $x = $point[0];
    $y = $point[1];
    $n = count($polygon);
    $inside = false;

    for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
        $xi = $polygon[$i][0]; $yi = $polygon[$i][1];
        $xj = $polygon[$j][0]; $yj = $polygon[$j][1];

        if ((($yi > $y) !== ($yj > $y)) &&
            ($x < ($xj - $xi) * ($y - $yi) / ($yj - $yi) + $xi)) {
            $inside = !$inside;
        }
    }
    return $inside;
}
