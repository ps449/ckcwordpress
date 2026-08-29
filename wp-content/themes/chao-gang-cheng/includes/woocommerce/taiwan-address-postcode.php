<?php
/**
 * 台灣縣市、鄉鎮市區與郵遞區號二級連動及自動代入模組
 *
 * 支援全台 22 縣市（本島 19 縣市 + 離島澎湖、金門、連江馬祖、綠島、蘭嶼、小琉球等）
 * 與 368 個鄉鎮市區 3 碼郵遞區號自動填入。
 *
 * @package ChaoGangCheng
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 取得全台 22 縣市清單（含本島 19 縣市與離島 3 縣市）
 */
function chao_get_taiwan_counties_list() {
    return array(
        ''    => '─ 請選擇縣市 ─',
        'TPE' => '台北市',
        'KEE' => '基隆市',
        'TPQ' => '新北市',
        'ILA' => '宜蘭縣',
        'HSZ' => '新竹市',
        'HSQ' => '新竹縣',
        'TAO' => '桃園市',
        'MIA' => '苗栗縣',
        'TXG' => '台中市',
        'CHA' => '彰化縣',
        'NAN' => '南投縣',
        'CYI' => '嘉義市',
        'CYQ' => '嘉義縣',
        'YUN' => '雲林縣',
        'TNN' => '台南市',
        'KHH' => '高雄市',
        'PIF' => '屏東縣',
        'TTT' => '台東縣',
        'HUA' => '花蓮縣',
        'PEN' => '澎湖縣',
        'KIN' => '金門縣',
        'LIE' => '連江縣',
    );
}

/**
 * 取得全台縣市、鄉鎮市區與郵遞區號完整資料字典
 *
 * @return array 結構為 [ 縣市代碼/名稱 => [ 鄉鎮市區 => 郵遞區號, ... ] ]
 */
function chao_get_taiwan_address_data() {
    static $data = null;
    if ( null !== $data ) {
        return $data;
    }

    $raw = array(
        'TPE' => array(
            'label' => '台北市',
            'aliases' => array( '臺北市', '台北市' ),
            'districts' => array(
                '中正區' => '100',
                '大同區' => '103',
                '中山區' => '104',
                '松山區' => '105',
                '大安區' => '106',
                '萬華區' => '108',
                '信義區' => '110',
                '士林區' => '111',
                '北投區' => '112',
                '內湖區' => '114',
                '南港區' => '115',
                '文山區' => '116',
            ),
        ),
        'KEE' => array(
            'label' => '基隆市',
            'aliases' => array( '基隆市' ),
            'districts' => array(
                '仁愛區' => '200',
                '信義區' => '201',
                '中正區' => '202',
                '中山區' => '203',
                '安樂區' => '204',
                '暖暖區' => '205',
                '七堵區' => '206',
            ),
        ),
        'TPQ' => array(
            'label' => '新北市',
            'aliases' => array( '新北市' ),
            'districts' => array(
                '萬里區' => '207',
                '金山區' => '208',
                '板橋區' => '220',
                '汐止區' => '221',
                '深坑區' => '222',
                '石碇區' => '223',
                '瑞芳區' => '224',
                '平溪區' => '226',
                '雙溪區' => '227',
                '貢寮區' => '228',
                '新店區' => '231',
                '坪林區' => '232',
                '烏來區' => '233',
                '永和區' => '234',
                '中和區' => '235',
                '土城區' => '236',
                '三峽區' => '237',
                '樹林區' => '238',
                '鶯歌區' => '239',
                '三重區' => '241',
                '新莊區' => '242',
                '泰山區' => '243',
                '林口區' => '244',
                '蘆洲區' => '247',
                '五股區' => '248',
                '八里區' => '249',
                '淡水區' => '251',
                '三芝區' => '252',
                '石門區' => '253',
            ),
        ),
        'ILA' => array(
            'label' => '宜蘭縣',
            'aliases' => array( '宜蘭縣' ),
            'districts' => array(
                '宜蘭市' => '260',
                '頭城鎮' => '261',
                '礁溪鄉' => '262',
                '壯圍鄉' => '263',
                '員山鄉' => '264',
                '羅東鎮' => '265',
                '三星鄉' => '266',
                '大同鄉' => '267',
                '五結鄉' => '268',
                '冬山鄉' => '269',
                '蘇澳鎮' => '270',
                '南澳鄉' => '272',
                '釣魚臺列嶼' => '290',
            ),
        ),
        'HSZ' => array(
            'label' => '新竹市',
            'aliases' => array( '新竹市' ),
            'districts' => array(
                '東區'   => '300',
                '北區'   => '300',
                '香山區' => '300',
            ),
        ),
        'HSQ' => array(
            'label' => '新竹縣',
            'aliases' => array( '新竹縣' ),
            'districts' => array(
                '竹北市' => '302',
                '湖口鄉' => '303',
                '新豐鄉' => '304',
                '新埔鎮' => '305',
                '關西鎮' => '306',
                '芎林鄉' => '307',
                '寶山鄉' => '308',
                '竹東鎮' => '310',
                '五峰鄉' => '311',
                '橫山鄉' => '312',
                '尖石鄉' => '313',
                '北埔鄉' => '314',
                '峨眉鄉' => '315',
            ),
        ),
        'TAO' => array(
            'label' => '桃園市',
            'aliases' => array( '桃園市' ),
            'districts' => array(
                '中壢區' => '320',
                '平鎮區' => '324',
                '龍潭區' => '325',
                '楊梅區' => '326',
                '新屋區' => '327',
                '觀音區' => '328',
                '桃園區' => '330',
                '龜山區' => '333',
                '八德區' => '334',
                '大溪區' => '335',
                '復興區' => '336',
                '大園區' => '337',
                '蘆竹區' => '338',
            ),
        ),
        'MIA' => array(
            'label' => '苗栗縣',
            'aliases' => array( '苗栗縣' ),
            'districts' => array(
                '竹南鎮' => '350',
                '頭份市' => '351',
                '三灣鄉' => '352',
                '南庄鄉' => '353',
                '獅潭鄉' => '354',
                '後龍鎮' => '356',
                '通霄鎮' => '357',
                '苑裡鎮' => '358',
                '苗栗市' => '360',
                '造橋鄉' => '361',
                '頭屋鄉' => '362',
                '公館鄉' => '363',
                '大湖鄉' => '364',
                '泰安鄉' => '365',
                '銅鑼鄉' => '366',
                '三義鄉' => '367',
                '西湖鄉' => '368',
                '卓蘭鎮' => '369',
            ),
        ),
        'TXG' => array(
            'label' => '台中市',
            'aliases' => array( '臺中市', '台中市' ),
            'districts' => array(
                '中區'   => '400',
                '東區'   => '401',
                '南區'   => '402',
                '西區'   => '403',
                '北區'   => '404',
                '北屯區' => '406',
                '西屯區' => '407',
                '南屯區' => '408',
                '太平區' => '411',
                '大里區' => '412',
                '霧峰區' => '413',
                '烏日區' => '414',
                '豐原區' => '420',
                '后里區' => '421',
                '石岡區' => '422',
                '東勢區' => '423',
                '和平區' => '424',
                '新社區' => '426',
                '潭子區' => '427',
                '大雅區' => '428',
                '神岡區' => '429',
                '大肚區' => '432',
                '沙鹿區' => '433',
                '龍井區' => '434',
                '梧棲區' => '435',
                '清水區' => '436',
                '大甲區' => '437',
                '外埔區' => '438',
                '大安區' => '439',
            ),
        ),
        'CHA' => array(
            'label' => '彰化縣',
            'aliases' => array( '彰化縣' ),
            'districts' => array(
                '彰化市' => '500',
                '芬園鄉' => '502',
                '花壇鄉' => '503',
                '秀水鄉' => '504',
                '鹿港鎮' => '505',
                '福興鄉' => '506',
                '線西鄉' => '507',
                '和美鎮' => '508',
                '伸港鄉' => '509',
                '員林市' => '510',
                '社頭鄉' => '511',
                '永靖鄉' => '512',
                '埔心鄉' => '513',
                '溪湖鎮' => '514',
                '大村鄉' => '515',
                '埔鹽鄉' => '516',
                '田中鎮' => '520',
                '北斗鎮' => '521',
                '田尾鄉' => '522',
                '埤頭鄉' => '523',
                '溪州鄉' => '524',
                '竹塘鄉' => '525',
                '二林鎮' => '526',
                '大城鄉' => '527',
                '芳苑鄉' => '528',
                '二水鄉' => '530',
            ),
        ),
        'NAN' => array(
            'label' => '南投縣',
            'aliases' => array( '南投縣' ),
            'districts' => array(
                '南投市' => '540',
                '中寮鄉' => '541',
                '草屯鎮' => '542',
                '國姓鄉' => '544',
                '埔里鎮' => '545',
                '仁愛鄉' => '546',
                '名間鄉' => '551',
                '集集鎮' => '552',
                '水里鄉' => '553',
                '魚池鄉' => '555',
                '信義鄉' => '556',
                '竹山鎮' => '557',
                '鹿谷鄉' => '558',
            ),
        ),
        'CYI' => array(
            'label' => '嘉義市',
            'aliases' => array( '嘉義市' ),
            'districts' => array(
                '東區' => '600',
                '西區' => '600',
            ),
        ),
        'CYQ' => array(
            'label' => '嘉義縣',
            'aliases' => array( '嘉義縣' ),
            'districts' => array(
                '番路鄉' => '602',
                '梅山鄉' => '603',
                '竹崎鄉' => '604',
                '阿里山鄉' => '605',
                '中埔鄉' => '606',
                '大埔鄉' => '607',
                '水上鄉' => '608',
                '鹿草鄉' => '609',
                '太保市' => '611',
                '朴子市' => '612',
                '東石鄉' => '613',
                '六腳鄉' => '614',
                '新港鄉' => '615',
                '民雄鄉' => '621',
                '大林鎮' => '622',
                '溪口鄉' => '623',
                '義竹鄉' => '624',
                '布袋鎮' => '625',
            ),
        ),
        'YUN' => array(
            'label' => '雲林縣',
            'aliases' => array( '雲林縣' ),
            'districts' => array(
                '斗南鎮' => '630',
                '大埤鄉' => '631',
                '虎尾鎮' => '632',
                '土庫鎮' => '633',
                '褒忠鄉' => '634',
                '東勢鄉' => '635',
                '台西鄉' => '636',
                '崙背鄉' => '637',
                '麥寮鄉' => '638',
                '斗六市' => '640',
                '林內鄉' => '643',
                '古坑鄉' => '646',
                '莿桐鄉' => '647',
                '西螺鎮' => '648',
                '二崙鄉' => '649',
                '北港鎮' => '651',
                '水林鄉' => '652',
                '口湖鄉' => '653',
                '四湖鄉' => '654',
                '元長鄉' => '655',
            ),
        ),
        'TNN' => array(
            'label' => '台南市',
            'aliases' => array( '臺南市', '台南市' ),
            'districts' => array(
                '中西區' => '700',
                '東區'   => '701',
                '南區'   => '702',
                '北區'   => '704',
                '安平區' => '708',
                '安南區' => '709',
                '永康區' => '710',
                '歸仁區' => '711',
                '新化區' => '712',
                '左鎮區' => '713',
                '玉井區' => '714',
                '楠西區' => '715',
                '南化區' => '716',
                '仁德區' => '717',
                '關廟區' => '718',
                '龍崎區' => '719',
                '官田區' => '720',
                '麻豆區' => '721',
                '佳里區' => '722',
                '西港區' => '723',
                '七股區' => '724',
                '將軍區' => '725',
                '學甲區' => '726',
                '北門區' => '727',
                '新營區' => '730',
                '後壁區' => '731',
                '白河區' => '732',
                '東山區' => '733',
                '六甲區' => '734',
                '下營區' => '735',
                '柳營區' => '736',
                '鹽水區' => '737',
                '善化區' => '741',
                '大內區' => '742',
                '山上區' => '743',
                '新市區' => '744',
                '安定區' => '745',
            ),
        ),
        'KHH' => array(
            'label' => '高雄市',
            'aliases' => array( '高雄市' ),
            'districts' => array(
                '新興區' => '800',
                '前金區' => '801',
                '苓雅區' => '802',
                '鹽埕區' => '803',
                '鼓山區' => '804',
                '旗津區' => '805',
                '前鎮區' => '806',
                '三民區' => '807',
                '楠梓區' => '811',
                '小港區' => '812',
                '左營區' => '813',
                '仁武區' => '814',
                '大社區' => '815',
                '東沙群島' => '817',
                '南沙群島' => '819',
                '岡山區' => '820',
                '路竹區' => '821',
                '阿蓮區' => '822',
                '田寮區' => '823',
                '燕巢區' => '824',
                '橋頭區' => '825',
                '梓官區' => '826',
                '彌陀區' => '827',
                '永安區' => '828',
                '湖內區' => '829',
                '鳳山區' => '830',
                '大寮區' => '831',
                '林園區' => '832',
                '鳥松區' => '833',
                '大樹區' => '840',
                '旗山區' => '842',
                '美濃區' => '843',
                '六龜區' => '844',
                '內門區' => '845',
                '杉林區' => '846',
                '甲仙區' => '847',
                '桃源區' => '848',
                '那瑪夏區' => '849',
                '茂林區' => '851',
                '茄萣區' => '852',
            ),
        ),
        'PIF' => array(
            'label' => '屏東縣',
            'aliases' => array( '屏東縣' ),
            'districts' => array(
                '屏東市' => '900',
                '三地門鄉' => '901',
                '霧台鄉' => '902',
                '瑪家鄉' => '903',
                '九如鄉' => '904',
                '里港鄉' => '905',
                '高樹鄉' => '906',
                '鹽埔鄉' => '907',
                '長治鄉' => '908',
                '麟洛鄉' => '909',
                '竹田鄉' => '911',
                '內埔鄉' => '912',
                '萬丹鄉' => '913',
                '潮州鎮' => '920',
                '泰武鄉' => '921',
                '來義鄉' => '922',
                '萬巒鄉' => '923',
                '崁頂鄉' => '924',
                '新埤鄉' => '925',
                '南州鄉' => '926',
                '林邊鄉' => '927',
                '東港鎮' => '928',
                '琉球鄉' => '929', // 離島（小琉球）
                '佳冬鄉' => '931',
                '新園鄉' => '932',
                '枋寮鄉' => '940',
                '枋山鄉' => '941',
                '春日鄉' => '942',
                '獅子鄉' => '943',
                '車城鄉' => '944',
                '牡丹鄉' => '945',
                '恆春鎮' => '946',
                '滿州鄉' => '947',
            ),
        ),
        'TTT' => array(
            'label' => '台東縣',
            'aliases' => array( '臺東縣', '台東縣' ),
            'districts' => array(
                '台東市' => '950',
                '綠島鄉' => '951', // 離島
                '蘭嶼鄉' => '952', // 離島
                '延平鄉' => '953',
                '卑南鄉' => '954',
                '鹿野鄉' => '955',
                '關山鎮' => '956',
                '海端鄉' => '957',
                '池上鄉' => '958',
                '東河鄉' => '959',
                '成功鎮' => '961',
                '長濱鄉' => '962',
                '太麻里鄉' => '963',
                '金峰鄉' => '964',
                '大武鄉' => '965',
                '達仁鄉' => '966',
            ),
        ),
        'HUA' => array(
            'label' => '花蓮縣',
            'aliases' => array( '花蓮縣' ),
            'districts' => array(
                '花蓮市' => '970',
                '新城鄉' => '971',
                '秀林鄉' => '972',
                '吉安鄉' => '973',
                '壽豐鄉' => '974',
                '鳳林鎮' => '975',
                '光復鄉' => '976',
                '豐濱鄉' => '977',
                '瑞穗鄉' => '978',
                '萬榮鄉' => '979',
                '玉里鎮' => '981',
                '卓溪鄉' => '982',
                '富里鄉' => '983',
            ),
        ),
        'PEN' => array(
            'label' => '澎湖縣',
            'aliases' => array( '澎湖縣' ),
            'districts' => array(
                '馬公市' => '880',
                '西嶼鄉' => '881',
                '望安鄉' => '882',
                '七美鄉' => '883',
                '白沙鄉' => '884',
                '湖西鄉' => '885',
            ),
        ),
        'KIN' => array(
            'label' => '金門縣',
            'aliases' => array( '金門縣' ),
            'districts' => array(
                '金沙鎮' => '890',
                '金湖鎮' => '891',
                '金寧鄉' => '892',
                '金城鎮' => '893',
                '烈嶼鄉' => '894',
                '烏坵鄉' => '896',
            ),
        ),
        'LIE' => array(
            'label' => '連江縣',
            'aliases' => array( '連江縣', '馬祖' ),
            'districts' => array(
                '南竿鄉' => '209',
                '北竿鄉' => '210',
                '莒光鄉' => '211',
                '東引鄉' => '212',
            ),
        ),
    );

    // 建立雙向查詢表（同時支援代碼與中文縣市名稱查詢）
    $data = array();
    foreach ( $raw as $code => $item ) {
        $data[ $code ] = $item['districts'];
        $data[ $item['label'] ] = $item['districts'];
        foreach ( $item['aliases'] as $alias ) {
            $data[ $alias ] = $item['districts'];
        }
    }

    return $data;
}

/**
 * 載入結帳頁與我的帳戶頁面台灣地址連動與郵遞區號自動代入 JavaScript & CSS
 */
add_action( 'wp_footer', 'chao_enqueue_taiwan_address_postcode_script', 30 );
function chao_enqueue_taiwan_address_postcode_script() {
    if ( ! is_checkout() && ! is_account_page() ) {
        return;
    }

    $tw_data    = chao_get_taiwan_address_data();
    $tw_counties = chao_get_taiwan_counties_list();
    ?>
    <style type="text/css">
    /* 台灣縣市與鄉鎮市區下拉選單美化樣式 */
    .chao-tw-select,
    select#billing_state,
    select#billing_city,
    select#shipping_state,
    select#shipping_city {
        width: 100% !important;
        height: 48px !important;
        line-height: 48px !important;
        padding: 0 40px 0 16px !important;
        border: 1px solid #dcdcde !important;
        border-radius: 8px !important;
        background-color: #fff !important;
        font-size: 15px !important;
        color: #1d2327 !important;
        box-sizing: border-box !important;
        appearance: none !important;
        -webkit-appearance: none !important;
        -moz-appearance: none !important;
        background-image: url("data:image/svg+xml;charset=UTF-8,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%2212%22%20height%3D%2212%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22%23333%22%20stroke-width%3D%222%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Cpolyline%20points%3D%226%209%2012%2015%2018%209%22%3E%3C%2Fpolyline%3E%3C%2Fsvg%3E") !important;
        background-repeat: no-repeat !important;
        background-position: right 16px center !important;
        background-size: 14px 14px !important;
        cursor: pointer !important;
        transition: border-color 0.2s ease, box-shadow 0.2s ease !important;
    }
    .chao-tw-select:focus,
    select#billing_state:focus,
    select#billing_city:focus,
    select#shipping_state:focus,
    select#shipping_city:focus {
        border-color: #c5a880 !important;
        box-shadow: 0 0 0 2px rgba(197, 168, 128, 0.25) !important;
        outline: none !important;
    }
    .woocommerce-input-wrapper select.chao-tw-select {
        display: block !important;
    }
    </style>

    <script type="text/javascript">
    (function($) {
        'use strict';

        var twAddressData = <?php echo wp_json_encode( $tw_data ); ?>;
        var twCountiesMap = <?php echo wp_json_encode( $tw_counties ); ?>;

        /**
         * 確保縣市欄位為 <select> 下拉選單
         */
        function ensureStateSelect(type) {
            var $stateField = $('#' + type + '_state');
            if ($stateField.length === 0) {
                return;
            }

            var currentVal = $stateField.val() || '';

            if (!$stateField.is('select')) {
                var optionsHtml = '';
                $.each(twCountiesMap, function(code, label) {
                    var isSelected = (code === currentVal || label === currentVal);
                    optionsHtml += '<option value="' + code + '"' + (isSelected ? ' selected="selected"' : '') + '>' + label + '</option>';
                });

                var $select = $('<select name="' + type + '_state" id="' + type + '_state" class="select chao-tw-select state_select">' + optionsHtml + '</select>');
                $stateField.replaceWith($select);
                $stateField = $select;
            } else {
                // 若已經是 select，檢查是否需要補充 className 與 options
                if (!$stateField.hasClass('chao-tw-select')) {
                    $stateField.addClass('chao-tw-select');
                }
                if ($stateField.find('option').length <= 1) {
                    var optionsHtml = '';
                    $.each(twCountiesMap, function(code, label) {
                        var isSelected = (code === currentVal || label === currentVal);
                        optionsHtml += '<option value="' + code + '"' + (isSelected ? ' selected="selected"' : '') + '>' + label + '</option>';
                    });
                    $stateField.html(optionsHtml);
                }
            }
        }

        /**
         * 根據選定的縣市更新鄉鎮市區下拉選單，並連動郵遞區號
         */
        function updateDistrictDropdown(type, preserveValue) {
            ensureStateSelect(type);

            var $stateField    = $('#' + type + '_state');
            var $cityField     = $('#' + type + '_city');
            var $postcodeField = $('#' + type + '_postcode');

            if ($stateField.length === 0 || $cityField.length === 0) {
                return;
            }

            var selectedState  = $stateField.val() || '';
            var currentCityVal = preserveValue || $cityField.val() || '';

            // 尋找對應的行政區清單
            var districts = twAddressData[selectedState] || null;

            var optionsHtml = '';
            var matchedZip   = '';

            if (!districts || selectedState === '') {
                optionsHtml = '<option value="">─ 請先選擇縣市 ─</option>';
            } else {
                optionsHtml = '<option value="">─ 請選擇鄉鎮市區 ─</option>';
                $.each(districts, function(districtName, zipCode) {
                    var isSelected = (districtName === currentCityVal);
                    if (isSelected) {
                        matchedZip = zipCode;
                    }
                    optionsHtml += '<option value="' + districtName + '" data-zip="' + zipCode + '"' + (isSelected ? ' selected="selected"' : '') + '>' + districtName + ' (' + zipCode + ')</option>';
                });
            }

            if (!$cityField.is('select')) {
                var $select = $('<select name="' + type + '_city" id="' + type + '_city" class="select chao-tw-select city_select">' + optionsHtml + '</select>');
                $cityField.replaceWith($select);
                $cityField = $select;
            } else {
                if (!$cityField.hasClass('chao-tw-select')) {
                    $cityField.addClass('chao-tw-select');
                }
                $cityField.html(optionsHtml);
                if (currentCityVal) {
                    $cityField.val(currentCityVal);
                }
            }

            // 若已有選取的鄉鎮市區，自動填入郵遞區號
            if (matchedZip && $postcodeField.length > 0) {
                if ($postcodeField.val() !== matchedZip) {
                    $postcodeField.val(matchedZip).trigger('change');
                }
            }
        }

        /**
         * 監聽縣市與鄉鎮市區選取變更，即時自動代入郵遞區號
         */
        function bindAddressEvents() {
            // 監聽縣市切換 (billing & shipping)
            $(document.body).on('change', 'select#billing_state, select#shipping_state', function() {
                var id = $(this).attr('id') || '';
                var type = (id.indexOf('shipping') !== -1) ? 'shipping' : 'billing';
                updateDistrictDropdown(type, '');
            });

            // 監聽鄉鎮市區切換，自動填入對應郵遞區號
            $(document.body).on('change', 'select#billing_city, select#shipping_city', function() {
                var $select = $(this);
                var id = $select.attr('id') || '';
                var type = (id.indexOf('shipping') !== -1) ? 'shipping' : 'billing';
                var selectedCity = $select.val() || '';
                var $stateField = $('#' + type + '_state');
                var $postcodeField = $('#' + type + '_postcode');

                var stateVal = $stateField.val() || '';
                var districts = twAddressData[stateVal] || null;

                if (districts && districts[selectedCity]) {
                    var zip = districts[selectedCity];
                    if ($postcodeField.length > 0) {
                        $postcodeField.val(zip).trigger('change');
                    }
                }
            });
        }

        /**
         * 初始化結帳頁面地址欄位
         */
        function initCheckoutAddress() {
            ['billing', 'shipping'].forEach(function(type) {
                var $stateField = $('#' + type + '_state');
                var $cityField = $('#' + type + '_city');
                if ($stateField.length > 0) {
                    var existingCity = $cityField.val() || '';
                    updateDistrictDropdown(type, existingCity);
                }
            });
        }

        $(document).ready(function() {
            bindAddressEvents();
            initCheckoutAddress();

            // 延遲再次確認 DOM 渲染完畢
            setTimeout(function() {
                initCheckoutAddress();
            }, 250);

            // 監聽 WooCommerce 結帳更新事件
            $(document.body).on('updated_checkout init_checkout', function() {
                initCheckoutAddress();
            });
        });
    })(jQuery);
    </script>
    <?php
}

/**
 * 於結帳欄位中設定縣市與鄉鎮市區為下拉選單 (type => select)
 */
add_filter( 'woocommerce_checkout_fields', 'chao_populate_checkout_districts_options', 99999 );
function chao_populate_checkout_districts_options( $fields ) {
    if ( is_admin() && ! wp_doing_ajax() ) {
        return $fields;
    }

    $tw_counties = chao_get_taiwan_counties_list();
    $tw_data     = chao_get_taiwan_address_data();
    $user_id     = get_current_user_id();

    foreach ( array( 'billing', 'shipping' ) as $type ) {
        // 1. 設定縣市欄位為 Select
        if ( isset( $fields[ $type ][ $type . '_state' ] ) ) {
            $fields[ $type ][ $type . '_state' ]['type']        = 'select';
            $fields[ $type ][ $type . '_state' ]['options']     = $tw_counties;
            $fields[ $type ][ $type . '_state' ]['placeholder'] = '請選擇縣市';
            $fields[ $type ][ $type . '_state' ]['class']       = array( 'form-row-wide' );
        }

        // 2. 設定鄉鎮市區欄位為 Select
        if ( isset( $fields[ $type ][ $type . '_city' ] ) ) {
            $fields[ $type ][ $type . '_city' ]['type']        = 'select';
            $fields[ $type ][ $type . '_city' ]['class']       = array( 'form-row-wide' );
            $fields[ $type ][ $type . '_city' ]['placeholder'] = '請選擇鄉鎮市區';

            $state = '';
            if ( isset( $_POST[ $type . '_state' ] ) ) {
                $state = sanitize_text_field( wp_unslash( $_POST[ $type . '_state' ] ) );
            } elseif ( $user_id ) {
                $state = get_user_meta( $user_id, $type . '_state', true );
            }

            if ( ! empty( $state ) && isset( $tw_data[ $state ] ) ) {
                $options = array( '' => '─ 請選擇鄉鎮市區 ─' );
                foreach ( $tw_data[ $state ] as $district_name => $zip ) {
                    $options[ $district_name ] = $district_name . ' (' . $zip . ')';
                }
                $fields[ $type ][ $type . '_city' ]['options'] = $options;
            } else {
                $fields[ $type ][ $type . '_city' ]['options'] = array( '' => '─ 請先選擇縣市 ─' );
            }
        }
    }

    return $fields;
}
