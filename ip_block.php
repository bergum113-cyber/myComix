<?php
/**
 * IP/국가 차단 시스템 + 브루트포스 방지
 * 
 * 기능:
 * - 특정 국가 차단 / 허용
 * - 특정 IP 차단 / 허용
 * - 화이트리스트 (항상 허용)
 * - 브루트포스 공격 방지 (로그인 시도 제한)
 * 
 * @version 2.0
 */

class IPBlocker {
    private $settings;
    private $settingsFile;
    private $cacheDir;
    private $geoipDbPath;
    private $geoipData = null;  // GeoIP CSV 데이터 캐시
    private $enabled = false;
    
    // ================================================================
    // 국가 코드 목록 (ISO 3166-1 alpha-2 + GeoIP 특수코드 = 255개)
    // ================================================================
    
    // 지역별 분류 (총 250개 + 특수코드 5개 = 255개)
    public static $regions = [
        '아시아' => [ // 27개
            'AF', 'BD', 'BN', 'BT', 'CN', 'HK', 'ID', 'IN', 'JP', 'KH', 'KP', 'KR', 
            'LA', 'LK', 'MM', 'MN', 'MO', 'MV', 'MY', 'NP', 'PH', 'PK', 'SG', 
            'TH', 'TL', 'TW', 'VN'
        ],
        '중동' => [ // 16개
            'AE', 'BH', 'CY', 'IL', 'IQ', 'IR', 'JO', 'KW', 'LB', 'OM', 'PS', 'QA', 
            'SA', 'SY', 'TR', 'YE'
        ],
        '유럽' => [ // 50개
            'AD', 'AL', 'AT', 'AX', 'BA', 'BE', 'BG', 'BY', 'CH', 'CZ', 'DE', 
            'DK', 'EE', 'ES', 'FI', 'FO', 'FR', 'GB', 'GG', 'GI', 'GR', 'HR', 
            'HU', 'IE', 'IM', 'IS', 'IT', 'JE', 'LI', 'LT', 'LU', 'LV', 'MC', 
            'MD', 'ME', 'MK', 'MT', 'NL', 'NO', 'PL', 'PT', 'RO', 'RS', 'RU', 
            'SE', 'SI', 'SK', 'SM', 'UA', 'VA'
        ],
        '북미' => [ // 38개 (북미 + 중앙아메리카 + 카리브해)
            'AG', 'AI', 'AW', 'BB', 'BM', 'BQ', 'BS', 'BZ', 'CA', 'CR', 'CU', 
            'CW', 'DM', 'DO', 'GD', 'GL', 'GP', 'GT', 'HN', 'HT', 'JM', 'KN', 
            'KY', 'LC', 'MQ', 'MS', 'MX', 'NI', 'PA', 'PM', 'PR', 'SV', 'TC', 
            'TT', 'US', 'VC', 'VG', 'VI'
        ],
        '남미' => [ // 14개
            'AR', 'BO', 'BR', 'CL', 'CO', 'EC', 'FK', 'GF', 'GY', 'PE', 'PY', 
            'SR', 'UY', 'VE'
        ],
        '오세아니아' => [ // 25개
            'AS', 'AU', 'CK', 'FJ', 'FM', 'GU', 'KI', 'MH', 'MP', 'NC', 'NF', 
            'NR', 'NU', 'NZ', 'PF', 'PG', 'PN', 'PW', 'SB', 'TK', 'TO', 'TV', 
            'VU', 'WF', 'WS'
        ],
        '아프리카' => [ // 58개
            'AO', 'BF', 'BI', 'BJ', 'BW', 'CD', 'CF', 'CG', 'CI', 'CM', 'CV', 
            'DJ', 'DZ', 'EG', 'EH', 'ER', 'ET', 'GA', 'GH', 'GM', 'GN', 'GQ', 
            'GW', 'KE', 'KM', 'LR', 'LS', 'LY', 'MA', 'MG', 'ML', 'MR', 'MU', 
            'MW', 'MZ', 'NA', 'NE', 'NG', 'RE', 'RW', 'SC', 'SD', 'SH', 'SL', 
            'SN', 'SO', 'SS', 'ST', 'SZ', 'TD', 'TG', 'TN', 'TZ', 'UG', 'YT', 
            'ZA', 'ZM', 'ZW'
        ],
        '중앙아시아' => [ // 8개
            'AM', 'AZ', 'GE', 'KG', 'KZ', 'TJ', 'TM', 'UZ'
        ],
        '기타' => [ // 14개 (남극, 속령 등)
            'AQ', 'BL', 'BV', 'CC', 'CX', 'GS', 'HM', 'IO', 'MF', 'SJ', 'SX', 
            'TF', 'UM', 'XK'
        ],
        '특수코드' => [ // 5개 (GeoIP 전용)
            'A1', 'A2', 'AP', 'EU', 'ZZ'
        ]
    ];
    
    // 전체 국가 코드 목록 (255개)
    public static $countries = [
        // 동아시아 (8)
        'CN' => '중국', 'HK' => '홍콩', 'JP' => '일본', 'KP' => '북한',
        'KR' => '대한민국', 'MO' => '마카오', 'MN' => '몽골', 'TW' => '대만',
        
        // 동남아시아 (11)
        'BN' => '브루나이', 'ID' => '인도네시아', 'KH' => '캄보디아', 'LA' => '라오스',
        'MM' => '미얀마', 'MY' => '말레이시아', 'PH' => '필리핀', 'SG' => '싱가포르',
        'TH' => '태국', 'TL' => '동티모르', 'VN' => '베트남',
        
        // 남아시아 (8)
        'AF' => '아프가니스탄', 'BD' => '방글라데시', 'BT' => '부탄', 'IN' => '인도',
        'LK' => '스리랑카', 'MV' => '몰디브', 'NP' => '네팔', 'PK' => '파키스탄',
        
        // 중앙아시아 (5)
        'KG' => '키르기스스탄', 'KZ' => '카자흐스탄', 'TJ' => '타지키스탄',
        'TM' => '투르크메니스탄', 'UZ' => '우즈베키스탄',
        
        // 서아시아/중동 (19)
        'AE' => '아랍에미리트', 'AM' => '아르메니아', 'AZ' => '아제르바이잔', 'BH' => '바레인',
        'CY' => '키프로스', 'GE' => '조지아', 'IL' => '이스라엘', 'IQ' => '이라크',
        'IR' => '이란', 'JO' => '요르단', 'KW' => '쿠웨이트', 'LB' => '레바논',
        'OM' => '오만', 'PS' => '팔레스타인', 'QA' => '카타르', 'SA' => '사우디아라비아',
        'SY' => '시리아', 'TR' => '튀르키예', 'YE' => '예멘',
        
        // 북유럽 (16)
        'AX' => '올란드제도', 'DK' => '덴마크', 'EE' => '에스토니아', 'FI' => '핀란드',
        'FO' => '페로제도', 'GB' => '영국', 'GG' => '건지', 'IE' => '아일랜드',
        'IM' => '맨섬', 'IS' => '아이슬란드', 'JE' => '저지', 'LT' => '리투아니아',
        'LV' => '라트비아', 'NO' => '노르웨이', 'SE' => '스웨덴', 'SJ' => '스발바르',
        
        // 서유럽 (9)
        'AT' => '오스트리아', 'BE' => '벨기에', 'CH' => '스위스', 'DE' => '독일',
        'FR' => '프랑스', 'LI' => '리히텐슈타인', 'LU' => '룩셈부르크', 'MC' => '모나코',
        'NL' => '네덜란드',
        
        // 남유럽 (17)
        'AD' => '안도라', 'AL' => '알바니아', 'BA' => '보스니아헤르체고비나', 'ES' => '스페인',
        'GI' => '지브롤터', 'GR' => '그리스', 'HR' => '크로아티아', 'IT' => '이탈리아',
        'ME' => '몬테네그로', 'MK' => '북마케도니아', 'MT' => '몰타', 'PT' => '포르투갈',
        'RS' => '세르비아', 'SI' => '슬로베니아', 'SM' => '산마리노', 'VA' => '바티칸',
        'XK' => '코소보',
        
        // 동유럽 (10)
        'BG' => '불가리아', 'BY' => '벨라루스', 'CZ' => '체코', 'HU' => '헝가리',
        'MD' => '몰도바', 'PL' => '폴란드', 'RO' => '루마니아', 'RU' => '러시아',
        'SK' => '슬로바키아', 'UA' => '우크라이나',
        
        // 북아메리카 (5)
        'BM' => '버뮤다', 'CA' => '캐나다', 'GL' => '그린란드', 'PM' => '생피에르미클롱',
        'US' => '미국',
        
        // 중앙아메리카/카리브해 (36)
        'AG' => '앤티가바부다', 'AI' => '앵귈라', 'AW' => '아루바', 'BB' => '바베이도스',
        'BL' => '생바르텔레미', 'BS' => '바하마', 'BQ' => '보네르', 'BZ' => '벨리즈',
        'CR' => '코스타리카', 'CU' => '쿠바', 'CW' => '퀴라소', 'DM' => '도미니카',
        'DO' => '도미니카공화국', 'GD' => '그레나다', 'GP' => '과들루프', 'GT' => '과테말라',
        'HN' => '온두라스', 'HT' => '아이티', 'JM' => '자메이카', 'KN' => '세인트키츠네비스',
        'KY' => '케이맨제도', 'LC' => '세인트루시아', 'MF' => '생마르탱', 'MQ' => '마르티니크',
        'MS' => '몬트세랫', 'MX' => '멕시코', 'NI' => '니카라과', 'PA' => '파나마',
        'PR' => '푸에르토리코', 'SV' => '엘살바도르', 'SX' => '신트마르턴', 'TC' => '터크스케이커스제도',
        'TT' => '트리니다드토바고', 'VC' => '세인트빈센트그레나딘', 'VG' => '영국령버진아일랜드',
        'VI' => '미국령버진아일랜드',
        
        // 남아메리카 (14)
        'AR' => '아르헨티나', 'BO' => '볼리비아', 'BR' => '브라질', 'CL' => '칠레',
        'CO' => '콜롬비아', 'EC' => '에콰도르', 'FK' => '포클랜드제도', 'GF' => '프랑스령기아나',
        'GY' => '가이아나', 'PE' => '페루', 'PY' => '파라과이', 'SR' => '수리남',
        'UY' => '우루과이', 'VE' => '베네수엘라',
        
        // 북아프리카 (8)
        'DZ' => '알제리', 'EG' => '이집트', 'EH' => '서사하라', 'LY' => '리비아',
        'MA' => '모로코', 'SD' => '수단', 'SS' => '남수단', 'TN' => '튀니지',
        
        // 서아프리카 (17)
        'BF' => '부르키나파소', 'BJ' => '베냉', 'CI' => '코트디부아르', 'CV' => '카보베르데',
        'GH' => '가나', 'GM' => '감비아', 'GN' => '기니', 'GW' => '기니비사우',
        'LR' => '라이베리아', 'ML' => '말리', 'MR' => '모리타니', 'NE' => '니제르',
        'NG' => '나이지리아', 'SH' => '세인트헬레나', 'SL' => '시에라리온', 'SN' => '세네갈',
        'TG' => '토고',
        
        // 중앙아프리카 (9)
        'AO' => '앙골라', 'CD' => '콩고민주공화국', 'CF' => '중앙아프리카공화국', 'CG' => '콩고',
        'CM' => '카메룬', 'GA' => '가봉', 'GQ' => '적도기니', 'ST' => '상투메프린시페',
        'TD' => '차드',
        
        // 동아프리카 (19)
        'BI' => '부룬디', 'DJ' => '지부티', 'ER' => '에리트레아', 'ET' => '에티오피아',
        'KE' => '케냐', 'KM' => '코모로', 'MG' => '마다가스카르', 'MU' => '모리셔스',
        'MW' => '말라위', 'MZ' => '모잠비크', 'RE' => '레위니옹', 'RW' => '르완다',
        'SC' => '세이셸', 'SO' => '소말리아', 'TZ' => '탄자니아', 'UG' => '우간다',
        'YT' => '마요트', 'ZM' => '잠비아', 'ZW' => '짐바브웨',
        
        // 남아프리카 (5)
        'BW' => '보츠와나', 'LS' => '레소토', 'NA' => '나미비아', 'SZ' => '에스와티니',
        'ZA' => '남아프리카공화국',
        
        // 오세아니아 (29)
        'AS' => '아메리칸사모아', 'AU' => '호주', 'CC' => '코코스제도', 'CK' => '쿡제도',
        'CX' => '크리스마스섬', 'FJ' => '피지', 'FM' => '미크로네시아', 'GU' => '괌',
        'HM' => '허드맥도널드제도', 'KI' => '키리바시', 'MH' => '마셜제도', 'MP' => '북마리아나제도',
        'NC' => '뉴칼레도니아', 'NF' => '노퍽섬', 'NR' => '나우루', 'NU' => '니우에',
        'NZ' => '뉴질랜드', 'PF' => '프랑스령폴리네시아', 'PG' => '파푸아뉴기니', 'PN' => '핏케언제도',
        'PW' => '팔라우', 'SB' => '솔로몬제도', 'TK' => '토켈라우', 'TO' => '통가',
        'TV' => '투발루', 'UM' => '미국령군소제도', 'VU' => '바누아투', 'WF' => '왈리스푸투나',
        'WS' => '사모아',
        
        // 남극/기타영토 (5)
        'AQ' => '남극', 'BV' => '부베섬', 'GS' => '사우스조지아', 'IO' => '영국령인도양지역',
        'TF' => '프랑스령남극지방',
        
        // 특수코드 - GeoIP (5)
        'A1' => '익명프록시', 'A2' => '위성통신', 'AP' => '아시아태평양', 'EU' => '유럽연합',
        'ZZ' => '알수없음'
    ];
    

    /**
     * 번역된 국가명 반환
     */
    public static function getTranslatedCountries() {
        $lang = function_exists('get_current_lang') ? get_current_lang() : 'ko';
        if ($lang === 'ko') return self::$countries;
        
        static $en_countries = [
            'A1' => 'Anonymous Proxy',
            'A2' => 'Satellite',
            'AD' => 'Andorra',
            'AE' => 'UAE',
            'AF' => 'Afghanistan',
            'AG' => 'Antigua & Barbuda',
            'AI' => 'Anguilla',
            'AL' => 'Albania',
            'AM' => 'Armenia',
            'AO' => 'Angola',
            'AP' => 'Asia-Pacific',
            'AQ' => 'Antarctica',
            'AR' => 'Argentina',
            'AS' => 'American Samoa',
            'AT' => 'Austria',
            'AU' => 'Australia',
            'AW' => 'Aruba',
            'AX' => 'Aland Islands',
            'AZ' => 'Azerbaijan',
            'BA' => 'Bosnia & Herzegovina',
            'BB' => 'Barbados',
            'BD' => 'Bangladesh',
            'BE' => 'Belgium',
            'BF' => 'Burkina Faso',
            'BG' => 'Bulgaria',
            'BH' => 'Bahrain',
            'BI' => 'Burundi',
            'BJ' => 'Benin',
            'BL' => 'Saint Barthelemy',
            'BM' => 'Bermuda',
            'BN' => 'Brunei',
            'BO' => 'Bolivia',
            'BQ' => 'Bonaire',
            'BR' => 'Brazil',
            'BS' => 'Bahamas',
            'BT' => 'Bhutan',
            'BV' => 'Bouvet Island',
            'BW' => 'Botswana',
            'BY' => 'Belarus',
            'BZ' => 'Belize',
            'CA' => 'Canada',
            'CC' => 'Cocos Islands',
            'CD' => 'DR Congo',
            'CF' => 'Central African Republic',
            'CG' => 'Congo',
            'CH' => 'Switzerland',
            'CI' => 'Ivory Coast',
            'CK' => 'Cook Islands',
            'CL' => 'Chile',
            'CM' => 'Cameroon',
            'CN' => 'China',
            'CO' => 'Colombia',
            'CR' => 'Costa Rica',
            'CU' => 'Cuba',
            'CV' => 'Cape Verde',
            'CW' => 'Curacao',
            'CX' => 'Christmas Island',
            'CY' => 'Cyprus',
            'CZ' => 'Czechia',
            'DE' => 'Germany',
            'DJ' => 'Djibouti',
            'DK' => 'Denmark',
            'DM' => 'Dominica',
            'DO' => 'Dominican Republic',
            'DZ' => 'Algeria',
            'EC' => 'Ecuador',
            'EE' => 'Estonia',
            'EG' => 'Egypt',
            'EH' => 'Western Sahara',
            'ER' => 'Eritrea',
            'ES' => 'Spain',
            'ET' => 'Ethiopia',
            'EU' => 'European Union',
            'FI' => 'Finland',
            'FJ' => 'Fiji',
            'FK' => 'Falkland Islands',
            'FM' => 'Micronesia',
            'FO' => 'Faroe Islands',
            'FR' => 'France',
            'GA' => 'Gabon',
            'GB' => 'United Kingdom',
            'GD' => 'Grenada',
            'GE' => 'Georgia',
            'GF' => 'French Guiana',
            'GG' => 'Guernsey',
            'GH' => 'Ghana',
            'GI' => 'Gibraltar',
            'GL' => 'Greenland',
            'GM' => 'Gambia',
            'GN' => 'Guinea',
            'GP' => 'Guadeloupe',
            'GQ' => 'Equatorial Guinea',
            'GR' => 'Greece',
            'GS' => 'South Georgia',
            'GT' => 'Guatemala',
            'GU' => 'Guam',
            'GW' => 'Guinea-Bissau',
            'GY' => 'Guyana',
            'HK' => 'Hong Kong',
            'HM' => 'Heard & McDonald Islands',
            'HN' => 'Honduras',
            'HR' => 'Croatia',
            'HT' => 'Haiti',
            'HU' => 'Hungary',
            'ID' => 'Indonesia',
            'IE' => 'Ireland',
            'IL' => 'Israel',
            'IM' => 'Isle of Man',
            'IN' => 'India',
            'IO' => 'British Indian Ocean Territory',
            'IQ' => 'Iraq',
            'IR' => 'Iran',
            'IS' => 'Iceland',
            'IT' => 'Italy',
            'JE' => 'Jersey',
            'JM' => 'Jamaica',
            'JO' => 'Jordan',
            'JP' => 'Japan',
            'KE' => 'Kenya',
            'KG' => 'Kyrgyzstan',
            'KH' => 'Cambodia',
            'KI' => 'Kiribati',
            'KM' => 'Comoros',
            'KN' => 'Saint Kitts & Nevis',
            'KP' => 'North Korea',
            'KR' => 'South Korea',
            'KW' => 'Kuwait',
            'KY' => 'Cayman Islands',
            'KZ' => 'Kazakhstan',
            'LA' => 'Laos',
            'LB' => 'Lebanon',
            'LC' => 'Saint Lucia',
            'LI' => 'Liechtenstein',
            'LK' => 'Sri Lanka',
            'LR' => 'Liberia',
            'LS' => 'Lesotho',
            'LT' => 'Lithuania',
            'LU' => 'Luxembourg',
            'LV' => 'Latvia',
            'LY' => 'Libya',
            'MA' => 'Morocco',
            'MC' => 'Monaco',
            'MD' => 'Moldova',
            'ME' => 'Montenegro',
            'MF' => 'Saint Martin',
            'MG' => 'Madagascar',
            'MH' => 'Marshall Islands',
            'MK' => 'North Macedonia',
            'ML' => 'Mali',
            'MM' => 'Myanmar',
            'MN' => 'Mongolia',
            'MO' => 'Macau',
            'MP' => 'Northern Mariana Islands',
            'MQ' => 'Martinique',
            'MR' => 'Mauritania',
            'MS' => 'Montserrat',
            'MT' => 'Malta',
            'MU' => 'Mauritius',
            'MV' => 'Maldives',
            'MW' => 'Malawi',
            'MX' => 'Mexico',
            'MY' => 'Malaysia',
            'MZ' => 'Mozambique',
            'NA' => 'Namibia',
            'NC' => 'New Caledonia',
            'NE' => 'Niger',
            'NF' => 'Norfolk Island',
            'NG' => 'Nigeria',
            'NI' => 'Nicaragua',
            'NL' => 'Netherlands',
            'NO' => 'Norway',
            'NP' => 'Nepal',
            'NR' => 'Nauru',
            'NU' => 'Niue',
            'NZ' => 'New Zealand',
            'OM' => 'Oman',
            'PA' => 'Panama',
            'PE' => 'Peru',
            'PF' => 'French Polynesia',
            'PG' => 'Papua New Guinea',
            'PH' => 'Philippines',
            'PK' => 'Pakistan',
            'PL' => 'Poland',
            'PM' => 'Saint Pierre & Miquelon',
            'PN' => 'Pitcairn Islands',
            'PR' => 'Puerto Rico',
            'PS' => 'Palestine',
            'PT' => 'Portugal',
            'PW' => 'Palau',
            'PY' => 'Paraguay',
            'QA' => 'Qatar',
            'RE' => 'Reunion',
            'RO' => 'Romania',
            'RS' => 'Serbia',
            'RU' => 'Russia',
            'RW' => 'Rwanda',
            'SA' => 'Saudi Arabia',
            'SB' => 'Solomon Islands',
            'SC' => 'Seychelles',
            'SD' => 'Sudan',
            'SE' => 'Sweden',
            'SG' => 'Singapore',
            'SH' => 'Saint Helena',
            'SI' => 'Slovenia',
            'SJ' => 'Svalbard',
            'SK' => 'Slovakia',
            'SL' => 'Sierra Leone',
            'SM' => 'San Marino',
            'SN' => 'Senegal',
            'SO' => 'Somalia',
            'SR' => 'Suriname',
            'SS' => 'South Sudan',
            'ST' => 'Sao Tome & Principe',
            'SV' => 'El Salvador',
            'SX' => 'Sint Maarten',
            'SY' => 'Syria',
            'SZ' => 'Eswatini',
            'TC' => 'Turks & Caicos',
            'TD' => 'Chad',
            'TF' => 'French Southern Territories',
            'TG' => 'Togo',
            'TH' => 'Thailand',
            'TJ' => 'Tajikistan',
            'TK' => 'Tokelau',
            'TL' => 'Timor-Leste',
            'TM' => 'Turkmenistan',
            'TN' => 'Tunisia',
            'TO' => 'Tonga',
            'TR' => 'Turkey',
            'TT' => 'Trinidad & Tobago',
            'TV' => 'Tuvalu',
            'TW' => 'Taiwan',
            'TZ' => 'Tanzania',
            'UA' => 'Ukraine',
            'UG' => 'Uganda',
            'UM' => 'US Minor Outlying Islands',
            'US' => 'United States',
            'UY' => 'Uruguay',
            'UZ' => 'Uzbekistan',
            'VA' => 'Vatican City',
            'VC' => 'Saint Vincent & Grenadines',
            'VE' => 'Venezuela',
            'VG' => 'British Virgin Islands',
            'VI' => 'US Virgin Islands',
            'VN' => 'Vietnam',
            'VU' => 'Vanuatu',
            'WF' => 'Wallis & Futuna',
            'WS' => 'Samoa',
            'XK' => 'Kosovo',
            'YE' => 'Yemen',
            'YT' => 'Mayotte',
            'ZA' => 'South Africa',
            'ZM' => 'Zambia',
            'ZW' => 'Zimbabwe',
            'ZZ' => 'Unknown',
        ];
        return $en_countries;
    }
    
    /**
     * 번역된 지역명 반환
     */
    public static function getTranslatedRegions() {
        $lang = function_exists('get_current_lang') ? get_current_lang() : 'ko';
        if ($lang === 'ko') return self::$regions;
        
        static $en_region_names = [
            '아시아' => 'Asia',
            '중동' => 'Middle East',
            '유럽' => 'Europe',
            '북미' => 'North America',
            '남미' => 'South America',
            '오세아니아' => 'Oceania',
            '아프리카' => 'Africa',
            '중앙아시아' => 'Central Asia',
            '기타' => 'Other',
            '특수코드' => 'Special Codes',
        ];
        
        $translated = [];
        foreach (self::$regions as $region_ko => $codes) {
            $region_name = $en_region_names[$region_ko] ?? $region_ko;
            $translated[$region_name] = $codes;
        }
        return $translated;
    }
    
    /**
     * 국가코드로 번역된 국가명 조회
     */
    public static function getCountryName($code) {
        $countries = self::getTranslatedCountries();
        return $countries[$code] ?? $code;
    }

    public function __construct($settingsFile = null) {
        $this->settingsFile = $settingsFile ?: __DIR__ . '/src/ip_block_settings.json';
        $this->cacheDir = __DIR__ . '/src/ip_cache';
        $this->geoipDbPath = __DIR__ . '/src/geoip.csv';  // GeoIP CSV 파일 경로
        $this->loadSettings();
    }
    
    /**
     * 설정 로드
     */
    public function loadSettings() {
        $default = [
            'enabled' => false,
            'mode' => [],
            'blocked_countries' => [],
            'allowed_countries' => [],
            'blocked_ips' => [],
            'allowed_ips' => [],
            'whitelist_ips' => [],
            'cache_hours' => 24,
            'block_message' => function_exists('__') ? __('ip_block_message') : '접근이 차단되었습니다.',
            'log_enabled' => true,
            // 브루트포스 방지 설정
            'bruteforce_enabled' => true,
            'bruteforce_max_attempts' => 5,
            'bruteforce_lockout_time' => 900, // 15분 (초)
            'bruteforce_attempt_window' => 300, // 5분 내 시도 횟수
            // ✅ 프록시 헤더 신뢰 설정 (IP 스푸핑 방지)
            // true: Cloudflare, Nginx 리버스 프록시 등 사용 시
            // false: 직접 연결 환경 (REMOTE_ADDR만 신뢰)
            'trust_proxy_headers' => true,
            // ✅ GeoIP 설정
            'geoip_source' => 'api',  // 'api', 'mmdb', 'dat', 또는 'csv'
            'geoip_mmdb_path' => '',  // MMDB 파일 경로 (비어있으면 기본 경로)
            'geoip_dat_path' => '',   // DAT 파일 경로 (비어있으면 기본 경로)
            'geoip_csv_path' => '',   // CSV 파일 경로 (비어있으면 기본 경로)
            'block_unknown' => false, // UNKNOWN IP 차단 여부
        ];
        
        if (file_exists($this->settingsFile)) {
            $loaded = json_decode(file_get_contents($this->settingsFile), true);
            $this->settings = array_merge($default, $loaded ?: []);
            if (!is_array($this->settings['mode'])) {
                $this->settings['mode'] = $this->settings['mode'] && $this->settings['mode'] !== 'disabled' 
                    ? [$this->settings['mode']] 
                    : [];
            }
        } else {
            $this->settings = $default;
        }
        
        $this->enabled = $this->settings['enabled'] && !empty($this->settings['mode']);
    }
    
    /**
     * 설정 저장
     */
    public function saveSettings($settings) {
        $this->settings = array_merge($this->settings, $settings);
        $dir = dirname($this->settingsFile);
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        @file_put_contents($this->settingsFile, json_encode($this->settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
        return true;
    }
    
    /**
     * 설정 가져오기
     */
    public function getSettings() {
        return $this->settings;
    }
    
    /**
     * 지역으로 국가 코드 가져오기
     */
    public static function getCountriesByRegion($region) {
        return self::$regions[$region] ?? [];
    }
    
    /**
     * 국가 코드의 지역 가져오기
     */
    public static function getRegionByCountry($countryCode) {
        foreach (self::$regions as $region => $countries) {
            if (in_array($countryCode, $countries)) {
                return $region;
            }
        }
        return null;
    }
    
    // ================================================================
    // 브루트포스 방지 시스템
    // ================================================================
    
    /**
     * 브루트포스 시도 파일 경로
     */
    private function getBruteforceFile() {
        return __DIR__ . '/src/bruteforce_attempts.json';
    }
    
    /**
     * 브루트포스 시도 기록 로드
     */
    private function loadBruteforceAttempts() {
        $file = $this->getBruteforceFile();
        if (!file_exists($file)) return [];
        $data = json_decode(file_get_contents($file), true);
        return is_array($data) ? $data : [];
    }
    
    /**
     * 브루트포스 시도 기록 저장
     */
    private function saveBruteforceAttempts($attempts) {
        $file = $this->getBruteforceFile();
        $dir = dirname($file);
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        @file_put_contents($file, json_encode($attempts, JSON_PRETTY_PRINT), LOCK_EX);
    }
    
    /**
     * IP가 브루트포스로 차단되었는지 확인
     * @return array ['blocked' => bool, 'remaining' => int (남은 잠금 시간, 초, -1=무제한), 'attempts' => int]
     */
    public function checkBruteforce($ip = null) {
        if (!($this->settings['bruteforce_enabled'] ?? true)) {
            return ['blocked' => false, 'remaining' => 0, 'attempts' => 0];
        }
        
        $ip = $ip ?: $this->getClientIP();
        $attempts = $this->loadBruteforceAttempts();
        $now = time();
        
        // 화이트리스트 IP는 체크 안 함
        if ($this->ipInList($ip, $this->settings['whitelist_ips'] ?? [])) {
            return ['blocked' => false, 'remaining' => 0, 'attempts' => 0];
        }
        
        if (!isset($attempts[$ip])) {
            return ['blocked' => false, 'remaining' => 0, 'attempts' => 0];
        }
        
        $record = $attempts[$ip];
        $lockoutTime = $this->settings['bruteforce_lockout_time'] ?? 900;
        
        // 잠금 상태 확인
        if (isset($record['locked_until'])) {
            // 무제한 잠금 (locked_until이 -1 또는 매우 큰 값)
            if ($record['locked_until'] == -1 || $record['locked_until'] > $now + 315360000) {
                return [
                    'blocked' => true,
                    'remaining' => -1, // 무제한
                    'attempts' => $record['count'] ?? 0
                ];
            }
            
            if ($record['locked_until'] > $now) {
                return [
                    'blocked' => true,
                    'remaining' => $record['locked_until'] - $now,
                    'attempts' => $record['count'] ?? 0
                ];
            }
        }
        
        // 잠금 해제됨 - 기록 초기화 (무제한이 아닌 경우만)
        if (isset($record['locked_until']) && $record['locked_until'] > 0 && $record['locked_until'] <= $now) {
            unset($attempts[$ip]);
            $this->saveBruteforceAttempts($attempts);
            return ['blocked' => false, 'remaining' => 0, 'attempts' => 0];
        }
        
        return [
            'blocked' => false,
            'remaining' => 0,
            'attempts' => $record['count'] ?? 0
        ];
    }
    
    /**
     * 로그인 실패 기록
     * @return array ['blocked' => bool, 'remaining' => int (-1=무제한), 'attempts' => int]
     */
    public function recordLoginFailure($ip = null, $username = '') {
        if (!($this->settings['bruteforce_enabled'] ?? true)) {
            return ['blocked' => false, 'remaining' => 0, 'attempts' => 0];
        }
        
        $ip = $ip ?: $this->getClientIP();
        $attempts = $this->loadBruteforceAttempts();
        $now = time();
        
        // 화이트리스트 IP는 기록 안 함
        if ($this->ipInList($ip, $this->settings['whitelist_ips'] ?? [])) {
            return ['blocked' => false, 'remaining' => 0, 'attempts' => 0];
        }
        
        $maxAttempts = $this->settings['bruteforce_max_attempts'] ?? 5;
        $attemptWindow = $this->settings['bruteforce_attempt_window'] ?? 300;
        $lockoutTime = $this->settings['bruteforce_lockout_time'] ?? 900;
        
        if (!isset($attempts[$ip])) {
            $attempts[$ip] = [
                'count' => 0,
                'first_attempt' => $now,
                'attempts' => []
            ];
        }
        
        $record = &$attempts[$ip];
        
        // 시도 기록 추가
        $record['attempts'][] = [
            'time' => $now,
            'username' => $username
        ];
        
        // 시간 윈도우 내 시도만 카운트
        $record['attempts'] = array_filter($record['attempts'], function($a) use ($now, $attemptWindow) {
            return ($now - $a['time']) <= $attemptWindow;
        });
        $record['attempts'] = array_values($record['attempts']); // 재인덱싱
        $record['count'] = count($record['attempts']);
        $record['last_attempt'] = $now;
        
        // 최대 시도 횟수 초과 시 잠금
        if ($record['count'] >= $maxAttempts) {
            // 무제한 잠금 (-1 또는 0)
            if ($lockoutTime <= 0) {
                $record['locked_until'] = -1; // 무제한 표시
            } else {
                $record['locked_until'] = $now + $lockoutTime;
            }
            $this->saveBruteforceAttempts($attempts);
            
            // 로그 기록
            $this->logBruteforce($ip, $username, $record['count']);
            
            return [
                'blocked' => true,
                'remaining' => $lockoutTime <= 0 ? -1 : $lockoutTime,
                'attempts' => $record['count']
            ];
        }
        
        $this->saveBruteforceAttempts($attempts);
        
        return [
            'blocked' => false,
            'remaining' => 0,
            'attempts' => $record['count']
        ];
    }
    
    /**
     * 로그인 성공 시 기록 초기화
     */
    public function clearLoginAttempts($ip = null) {
        $ip = $ip ?: $this->getClientIP();
        $attempts = $this->loadBruteforceAttempts();
        
        if (isset($attempts[$ip])) {
            unset($attempts[$ip]);
            $this->saveBruteforceAttempts($attempts);
        }
    }
    
    /**
     * 브루트포스 로그 기록
     */
    private function logBruteforce($ip, $username, $attempts) {
        if (!($this->settings['log_enabled'] ?? true)) return;
        
        $logFile = __DIR__ . '/src/bruteforce_log.json';
        $logs = [];
        
        if (file_exists($logFile)) {
            $logs = json_decode(file_get_contents($logFile), true) ?: [];
        }
        
        // 최근 500개만 유지
        if (count($logs) >= 500) {
            $logs = array_slice($logs, -499);
        }
        
        $logs[] = [
            'time' => date('Y-m-d H:i:s'),
            'ip' => $ip,
            'username' => $username,
            'attempts' => $attempts,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ];
        
        @file_put_contents($logFile, json_encode($logs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
    }
    
    /**
     * 브루트포스 로그 가져오기
     */
    /**
     * 브루트포스 로그 가져오기 (페이지네이션 + 날짜 필터 지원)
     * @param int $limit 한 페이지당 로그 수
     * @param int $page 페이지 번호 (1부터 시작)
     * @param string|null $dateFrom 시작 날짜 (Y-m-d)
     * @param string|null $dateTo 끝 날짜 (Y-m-d)
     * @return array ['logs' => [], 'total' => int, 'pages' => int]
     */
    public function getBruteforceLogs($limit = 50, $page = 1, $dateFrom = null, $dateTo = null) {
        $logFile = __DIR__ . '/src/bruteforce_log.json';
        if (!file_exists($logFile)) return ['logs' => [], 'total' => 0, 'pages' => 0];
        
        $logs = json_decode(file_get_contents($logFile), true) ?: [];
        
        // 날짜 필터 적용
        if ($dateFrom || $dateTo) {
            $logs = array_filter($logs, function($log) use ($dateFrom, $dateTo) {
                $logDate = substr($log['time'] ?? '', 0, 10);
                if ($dateFrom && $logDate < $dateFrom) return false;
                if ($dateTo && $logDate > $dateTo) return false;
                return true;
            });
        }
        
        // 역순 정렬 (최신순)
        $logs = array_reverse($logs);
        $total = count($logs);
        $pages = ceil($total / $limit);
        $offset = ($page - 1) * $limit;
        
        return [
            'logs' => array_slice($logs, $offset, $limit),
            'total' => $total,
            'pages' => $pages
        ];
    }
    
    /**
     * 브루트포스 로그 선택 삭제
     * @param array $indices 삭제할 로그 인덱스들
     */
    public function deleteBruteforceLogsByIndex($indices) {
        $logFile = __DIR__ . '/src/bruteforce_log.json';
        if (!file_exists($logFile)) return false;
        
        $logs = json_decode(file_get_contents($logFile), true) ?: [];
        $logs = array_reverse($logs); // 최신순으로
        
        // 인덱스 기준 삭제
        foreach ($indices as $idx) {
            unset($logs[$idx]);
        }
        
        $logs = array_values(array_reverse($logs)); // 원래 순서로 복원
        @file_put_contents($logFile, json_encode($logs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
        return true;
    }
    
    /**
     * 브루트포스 로그 날짜 범위 삭제
     */
    public function deleteBruteforceLogsByDateRange($dateFrom, $dateTo) {
        $logFile = __DIR__ . '/src/bruteforce_log.json';
        if (!file_exists($logFile)) return false;
        
        $logs = json_decode(file_get_contents($logFile), true) ?: [];
        $logs = array_filter($logs, function($log) use ($dateFrom, $dateTo) {
            $logDate = substr($log['time'] ?? '', 0, 10);
            if ($dateFrom && $logDate >= $dateFrom && (!$dateTo || $logDate <= $dateTo)) return false;
            return true;
        });
        
        @file_put_contents($logFile, json_encode(array_values($logs), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
        return true;
    }
    
    /**
     * 브루트포스 기록 초기화
     */
    public function clearBruteforceData() {
        $attemptFile = $this->getBruteforceFile();
        $logFile = __DIR__ . '/src/bruteforce_log.json';
        
        if (file_exists($attemptFile)) @unlink($attemptFile);
        if (file_exists($logFile)) @unlink($logFile);
        
        return true;
    }
    
    /**
     * 특정 IP의 브루트포스 잠금 해제
     */
    public function unlockIP($ip) {
        $attempts = $this->loadBruteforceAttempts();
        if (isset($attempts[$ip])) {
            unset($attempts[$ip]);
            $this->saveBruteforceAttempts($attempts);
            return true;
        }
        return false;
    }
    
    /**
     * 현재 잠긴 IP 목록
     */
    public function getLockedIPs() {
        $attempts = $this->loadBruteforceAttempts();
        $now = time();
        $locked = [];
        
        foreach ($attempts as $ip => $record) {
            if (isset($record['locked_until'])) {
                // 무제한 잠금
                if ($record['locked_until'] == -1 || $record['locked_until'] > $now + 315360000) {
                    $locked[] = [
                        'ip' => $ip,
                        'locked_until' => function_exists('__') ? __('ip_unlimited') : '무제한',
                        'remaining' => -1,
                        'attempts' => $record['count'] ?? 0
                    ];
                }
                // 일반 잠금
                else if ($record['locked_until'] > $now) {
                    $locked[] = [
                        'ip' => $ip,
                        'locked_until' => date('Y-m-d H:i:s', $record['locked_until']),
                        'remaining' => $record['locked_until'] - $now,
                        'attempts' => $record['count'] ?? 0
                    ];
                }
            }
        }
        
        return $locked;
    }
    
    // ================================================================
    // IP/국가 차단 시스템 (기존 기능)
    // ================================================================
    
    /**
     * 클라이언트 IP 가져오기
     * 
     * ✅ trust_proxy_headers 설정에 따라 동작:
     * - true: 프록시 헤더(X-Forwarded-For 등) 신뢰 (CDN/리버스 프록시 환경)
     * - false: REMOTE_ADDR만 신뢰 (직접 연결 환경, IP 스푸핑 방지)
     */
    public function getClientIP() {
        // ✅ 프록시 헤더 신뢰 여부 확인
        $trust_proxy = $this->settings['trust_proxy_headers'] ?? true;
        
        if ($trust_proxy) {
            // 프록시 환경: 헤더 순서대로 확인
            $headers = [
                'HTTP_CF_CONNECTING_IP',   // Cloudflare
                'HTTP_X_FORWARDED_FOR',    // 일반 프록시/로드밸런서
                'HTTP_X_REAL_IP',          // Nginx
                'HTTP_CLIENT_IP',          // 일부 프록시
                'REMOTE_ADDR'              // 최종 폴백
            ];
            
            foreach ($headers as $header) {
                if (!empty($_SERVER[$header])) {
                    $ip = $_SERVER[$header];
                    // X-Forwarded-For는 쉼표로 구분된 IP 목록일 수 있음 (첫 번째가 실제 클라이언트)
                    if (strpos($ip, ',') !== false) {
                        $ip = trim(explode(',', $ip)[0]);
                    }
                    if (filter_var($ip, FILTER_VALIDATE_IP)) {
                        return $ip;
                    }
                }
            }
        }
        
        // 직접 연결 또는 폴백: REMOTE_ADDR만 사용
        return filter_var($_SERVER['REMOTE_ADDR'] ?? '', FILTER_VALIDATE_IP) ?: '0.0.0.0';
    }
    
    /**
     * IP로 국가 코드 가져오기 (캐싱 포함)
     */
    public function getCountryByIP($ip) {
        if ($this->isPrivateIP($ip)) {
            return 'LOCAL';
        }
        
        $cached = $this->getCachedCountry($ip);
        if ($cached !== null) {
            return $cached;
        }
        
        $country = null;
        $source = $this->settings['geoip_source'] ?? 'api';
        
        // MMDB DB 사용 (MaxMind GeoLite2)
        if ($source === 'mmdb') {
            $country = $this->getCountryFromMMDB($ip);
        }
        // DAT DB 사용 (레거시 GeoIP.dat)
        elseif ($source === 'dat') {
            $country = $this->getCountryFromDAT($ip);
        }
        // CSV DB 사용
        elseif ($source === 'csv') {
            $country = $this->getCountryFromCSV($ip);
        }
        // API 사용 (ip-api.com)
        elseif ($source === 'api') {
            $country = $this->fetchCountryFromAPI($ip);
        }
        
        // fallback: MMDB/DAT/CSV에서 못 찾으면 API 시도
        if (!$country && $source !== 'api') {
            $country = $this->fetchCountryFromAPI($ip);
        }
        
        if ($country) {
            $this->cacheCountry($ip, $country);
        }
        
        return $country ?: 'UNKNOWN';
    }
    
    /**
     * MaxMind GeoLite2 MMDB 파일에서 국가 코드 가져오기
     */
    private function getCountryFromMMDB($ip) {
        $mmdbPath = $this->settings['geoip_mmdb_path'] ?? '';
        if (empty($mmdbPath)) {
            $mmdbPath = __DIR__ . '/src/GeoLite2-Country.mmdb';
        }
        
        if (!file_exists($mmdbPath)) {
            return null;
        }
        
        // MaxMindReader 로드
        static $reader = null;
        static $readerPath = null;
        
        if ($reader === null || $readerPath !== $mmdbPath) {
            $readerFile = __DIR__ . '/src/MaxMindReader.php';
            if (!file_exists($readerFile)) {
                return null;
            }
            require_once $readerFile;
            
            try {
                $reader = new MaxMindReader($mmdbPath);
                $readerPath = $mmdbPath;
            } catch (Exception $e) {
                error_log("GeoIP MMDB Error: " . $e->getMessage());
                return null;
            }
        }
        
        try {
            return $reader->getCountry($ip);
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * 레거시 GeoIP.dat 파일에서 국가 코드 가져오기
     */
    private function getCountryFromDAT($ip) {
        $datPath = $this->settings['geoip_dat_path'] ?? '';
        if (empty($datPath)) {
            $datPath = __DIR__ . '/src/GeoIP.dat';
        }
        
        if (!file_exists($datPath)) {
            return null;
        }
        
        // GeoIP 확장이 설치되어 있으면 사용
        if (function_exists('geoip_country_code_by_addr')) {
            return @geoip_country_code_by_addr($ip);
        }
        
        // 순수 PHP로 GeoIP.dat 읽기
        static $geoipHandle = null;
        static $geoipPath = null;
        
        if ($geoipHandle === null || $geoipPath !== $datPath) {
            if ($geoipHandle) {
                fclose($geoipHandle);
            }
            $geoipHandle = @fopen($datPath, 'rb');
            $geoipPath = $datPath;
            
            if (!$geoipHandle) {
                return null;
            }
        }
        
        // 국가 코드 목록 (GeoIP.dat 표준 순서)
        $countryList = [
            '', 'AP', 'EU', 'AD', 'AE', 'AF', 'AG', 'AI', 'AL', 'AM', 'CW',
            'AO', 'AQ', 'AR', 'AS', 'AT', 'AU', 'AW', 'AZ', 'BA', 'BB',
            'BD', 'BE', 'BF', 'BG', 'BH', 'BI', 'BJ', 'BM', 'BN', 'BO',
            'BR', 'BS', 'BT', 'BV', 'BW', 'BY', 'BZ', 'CA', 'CC', 'CD',
            'CF', 'CG', 'CH', 'CI', 'CK', 'CL', 'CM', 'CN', 'CO', 'CR',
            'CU', 'CV', 'CX', 'CY', 'CZ', 'DE', 'DJ', 'DK', 'DM', 'DO',
            'DZ', 'EC', 'EE', 'EG', 'EH', 'ER', 'ES', 'ET', 'FI', 'FJ',
            'FK', 'FM', 'FO', 'FR', 'SX', 'GA', 'GB', 'GD', 'GE', 'GF',
            'GH', 'GI', 'GL', 'GM', 'GN', 'GP', 'GQ', 'GR', 'GS', 'GT',
            'GU', 'GW', 'GY', 'HK', 'HM', 'HN', 'HR', 'HT', 'HU', 'ID',
            'IE', 'IL', 'IN', 'IO', 'IQ', 'IR', 'IS', 'IT', 'JM', 'JO',
            'JP', 'KE', 'KG', 'KH', 'KI', 'KM', 'KN', 'KP', 'KR', 'KW',
            'KY', 'KZ', 'LA', 'LB', 'LC', 'LI', 'LK', 'LR', 'LS', 'LT',
            'LU', 'LV', 'LY', 'MA', 'MC', 'MD', 'MG', 'MH', 'MK', 'ML',
            'MM', 'MN', 'MO', 'MP', 'MQ', 'MR', 'MS', 'MT', 'MU', 'MV',
            'MW', 'MX', 'MY', 'MZ', 'NA', 'NC', 'NE', 'NF', 'NG', 'NI',
            'NL', 'NO', 'NP', 'NR', 'NU', 'NZ', 'OM', 'PA', 'PE', 'PF',
            'PG', 'PH', 'PK', 'PL', 'PM', 'PN', 'PR', 'PS', 'PT', 'PW',
            'PY', 'QA', 'RE', 'RO', 'RU', 'RW', 'SA', 'SB', 'SC', 'SD',
            'SE', 'SG', 'SH', 'SI', 'SJ', 'SK', 'SL', 'SM', 'SN', 'SO',
            'SR', 'ST', 'SV', 'SY', 'SZ', 'TC', 'TD', 'TF', 'TG', 'TH',
            'TJ', 'TK', 'TM', 'TN', 'TO', 'TL', 'TR', 'TT', 'TV', 'TW',
            'TZ', 'UA', 'UG', 'UM', 'US', 'UY', 'UZ', 'VA', 'VC', 'VE',
            'VG', 'VI', 'VN', 'VU', 'WF', 'WS', 'YE', 'YT', 'RS', 'ZA',
            'ZM', 'ME', 'ZW', 'A1', 'A2', 'O1', 'AX', 'GG', 'IM', 'JE',
            'BL', 'MF', 'BQ', 'SS', 'XK'
        ];
        
        $ipLong = ip2long($ip);
        if ($ipLong === false) {
            return null;
        }
        $ipLong = sprintf('%u', $ipLong);
        
        // GeoIP.dat 구조에서 국가 인덱스 찾기
        $offset = 0;
        for ($depth = 31; $depth >= 0; $depth--) {
            fseek($geoipHandle, 2 * 3 * $offset);
            $buf = fread($geoipHandle, 6);
            
            if (strlen($buf) < 6) {
                return null;
            }
            
            $x = [0, 0];
            for ($i = 0; $i < 2; $i++) {
                for ($j = 0; $j < 3; $j++) {
                    $x[$i] += ord($buf[3 * $i + $j]) << ($j * 8);
                }
            }
            
            $bit = ($ipLong >> $depth) & 1;
            $offset = $x[$bit];
            
            if ($offset >= 16776960) {
                $countryIndex = $offset - 16776960;
                if (isset($countryList[$countryIndex])) {
                    return $countryList[$countryIndex] ?: null;
                }
                return null;
            }
        }
        
        return null;
    }
    
    /**
     * GeoIP CSV 파일에서 국가 코드 가져오기
     * CSV 형식: IP_FROM,IP_TO,COUNTRY_CODE (숫자 형식)
     * 예: "0","16777215","US"
     */
    private function getCountryFromCSV($ip) {
        $csvPath = $this->settings['geoip_csv_path'] ?? '';
        if (empty($csvPath)) {
            $csvPath = $this->geoipDbPath;
        }
        
        if (!file_exists($csvPath)) {
            return null;
        }
        
        $ipLong = ip2long($ip);
        if ($ipLong === false) {
            return null;
        }
        
        // unsigned로 변환 (음수 방지)
        $ipLong = sprintf('%u', $ipLong);
        
        // CSV 데이터 로드 (첫 호출 시만)
        if ($this->geoipData === null) {
            $this->loadGeoIPData($csvPath);
        }
        
        if (empty($this->geoipData)) {
            return null;
        }
        
        // 이진 검색으로 IP 범위 찾기
        return $this->binarySearchCountry($ipLong);
    }
    
    /**
     * GeoIP CSV 데이터 로드
     */
    private function loadGeoIPData($csvPath) {
        $this->geoipData = [];
        
        $handle = @fopen($csvPath, 'r');
        if (!$handle) {
            return;
        }
        
        while (($line = fgetcsv($handle)) !== false) {
            if (count($line) >= 3) {
                // IP_FROM, IP_TO, COUNTRY_CODE
                $this->geoipData[] = [
                    'from' => (float)str_replace('"', '', $line[0]),
                    'to' => (float)str_replace('"', '', $line[1]),
                    'cc' => strtoupper(trim(str_replace('"', '', $line[2])))
                ];
            }
        }
        
        fclose($handle);
    }
    
    /**
     * 이진 검색으로 IP에 해당하는 국가 찾기
     */
    private function binarySearchCountry($ipLong) {
        $left = 0;
        $right = count($this->geoipData) - 1;
        $ipLong = (float)$ipLong;
        
        while ($left <= $right) {
            $mid = (int)(($left + $right) / 2);
            $range = $this->geoipData[$mid];
            
            if ($ipLong >= $range['from'] && $ipLong <= $range['to']) {
                return $range['cc'];
            }
            
            if ($ipLong < $range['from']) {
                $right = $mid - 1;
            } else {
                $left = $mid + 1;
            }
        }
        
        return null;
    }

    
    /**
     * 사설 IP 확인
     */
    private function isPrivateIP($ip) {
        return !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }
    
    /**
     * 캐시된 국가 코드 가져오기
     */
    private function getCachedCountry($ip) {
        if (!is_dir($this->cacheDir)) return null;
        
        $cacheFile = $this->cacheDir . '/' . md5($ip) . '.json';
        if (!file_exists($cacheFile)) return null;
        
        $data = json_decode(file_get_contents($cacheFile), true);
        if (!$data) return null;
        
        $cacheHours = $this->settings['cache_hours'] ?? 24;
        if (time() - ($data['time'] ?? 0) > $cacheHours * 3600) {
            @unlink($cacheFile);
            return null;
        }
        
        return $data['country'] ?? null;
    }
    
    /**
     * 국가 코드 캐시 저장
     */
    private function cacheCountry($ip, $country) {
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
        
        $cacheFile = $this->cacheDir . '/' . md5($ip) . '.json';
        @file_put_contents($cacheFile, json_encode([
            'ip' => $ip,
            'country' => $country,
            'time' => time()
        ]), LOCK_EX);
    }
    
    /**
     * API에서 국가 코드 가져오기 (cURL 사용)
     */
    private function fetchCountryFromAPI($ip) {
        $url = "http://ip-api.com/json/{$ip}?fields=status,countryCode";
        
        // cURL 사용 (allow_url_fopen = Off 환경 지원)
        if (function_exists('curl_init')) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 3);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
            $response = curl_exec($ch);
            $error = curl_error($ch);
            curl_close($ch);
            
            if ($response && !$error) {
                $data = json_decode($response, true);
                if ($data && ($data['status'] ?? '') === 'success') {
                    return $data['countryCode'] ?? null;
                }
            }
            return null;
        }
        
        // fallback: file_get_contents (allow_url_fopen = On 필요)
        $ctx = stream_context_create([
            'http' => [
                'timeout' => 3,
                'ignore_errors' => true
            ]
        ]);
        
        $response = @file_get_contents($url, false, $ctx);
        if ($response) {
            $data = json_decode($response, true);
            if ($data && ($data['status'] ?? '') === 'success') {
                return $data['countryCode'] ?? null;
            }
        }
        
        return null;
    }
    
    /**
     * IP가 CIDR 범위에 포함되는지 확인
     */
    public function ipInRange($ip, $range) {
        if (strpos($range, '/') === false) {
            return $ip === $range;
        }
        
        list($subnet, $bits) = explode('/', $range);
        
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $ip_long = ip2long($ip);
            $subnet_long = ip2long($subnet);
            $mask = -1 << (32 - (int)$bits);
            return ($ip_long & $mask) === ($subnet_long & $mask);
        }
        
        return false;
    }
    
    /**
     * IP가 목록에 있는지 확인
     */
    public function ipInList($ip, $list) {
        foreach ($list as $item) {
            if ($this->ipInRange($ip, trim($item))) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * 접근 차단 여부 확인
     */
    public function checkAccess($ip = null) {
        if (!$this->enabled) {
            return ['blocked' => false, 'reason' => '', 'country' => ''];
        }
        
        $ip = $ip ?: $this->getClientIP();
        $result = ['blocked' => false, 'reason' => '', 'country' => '', 'ip' => $ip];
        
        // 화이트리스트 확인
        if ($this->ipInList($ip, $this->settings['whitelist_ips'] ?? [])) {
            return $result;
        }
        
        $modes = $this->settings['mode'] ?? [];
        if (!is_array($modes)) {
            $modes = $modes && $modes !== 'disabled' ? [$modes] : [];
        }
        
        // 국가 정보
        $country = null;
        $needsCountry = array_intersect($modes, ['block_countries', 'allow_countries']);
        $blockUnknown = $this->settings['block_unknown'] ?? false;
        
        if (!empty($needsCountry) || $blockUnknown) {
            $country = $this->getCountryByIP($ip);
            $result['country'] = $country;
        }
        
        // UNKNOWN IP 차단 (GeoIP에서 국가를 확인할 수 없는 경우)
        if ($blockUnknown && $country === 'UNKNOWN') {
            $result['blocked'] = true;
            $result['reason'] = "blocked_unknown_country";
            if ($this->settings['log_enabled'] ?? true) {
                $this->logBlock($result);
            }
            return $result;
        }
        
        // 특정 국가 차단
        if (in_array('block_countries', $modes)) {
            $blockedCountries = $this->settings['blocked_countries'] ?? [];
            if (in_array($country, $blockedCountries)) {
                $result['blocked'] = true;
                $result['reason'] = "blocked_country:{$country}";
            }
        }
        
        // 특정 IP 차단
        if (in_array('block_ips', $modes)) {
            $blockedIPs = $this->settings['blocked_ips'] ?? [];
            if ($this->ipInList($ip, $blockedIPs)) {
                $result['blocked'] = true;
                $result['reason'] = $result['reason'] 
                    ? $result['reason'] . ", blocked_ip:{$ip}" 
                    : "blocked_ip:{$ip}";
            }
        }
        
        if ($result['blocked']) {
            if ($this->settings['log_enabled'] ?? true) {
                $this->logBlock($result);
            }
            return $result;
        }
        
        // 특정 국가만 허용
        if (in_array('allow_countries', $modes)) {
            $allowedCountries = $this->settings['allowed_countries'] ?? [];
            if ($country !== 'LOCAL' && $country !== 'UNKNOWN' && !in_array($country, $allowedCountries)) {
                $result['blocked'] = true;
                $result['reason'] = "not_allowed_country:{$country}";
            }
        }
        
        // 특정 IP만 허용
        if (in_array('allow_ips', $modes)) {
            $allowedIPs = $this->settings['allowed_ips'] ?? [];
            if (!$this->isPrivateIP($ip) && !$this->ipInList($ip, $allowedIPs)) {
                $result['blocked'] = true;
                $result['reason'] = $result['reason'] 
                    ? $result['reason'] . ", not_allowed_ip:{$ip}" 
                    : "not_allowed_ip:{$ip}";
            }
        }
        
        if ($result['blocked'] && ($this->settings['log_enabled'] ?? true)) {
            $this->logBlock($result);
        }
        
        return $result;
    }
    
    /**
     * 차단 로그 기록
     */
    private function logBlock($result) {
        $logFile = __DIR__ . '/src/ip_block_log.json';
        $logs = [];
        
        if (file_exists($logFile)) {
            $logs = json_decode(file_get_contents($logFile), true) ?: [];
        }
        
        if (count($logs) >= 1000) {
            $logs = array_slice($logs, -999);
        }
        
        $logs[] = [
            'time' => date('Y-m-d H:i:s'),
            'ip' => $result['ip'],
            'country' => $result['country'],
            'reason' => $result['reason'],
            'uri' => $_SERVER['REQUEST_URI'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ];
        
        @file_put_contents($logFile, json_encode($logs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
    }
    
    /**
     * 차단 로그 가져오기
     */
    /**
     * 차단 로그 가져오기 (페이지네이션 + 날짜 필터 지원)
     * @param int $limit 한 페이지당 로그 수
     * @param int $page 페이지 번호 (1부터 시작)
     * @param string|null $dateFrom 시작 날짜 (Y-m-d)
     * @param string|null $dateTo 끝 날짜 (Y-m-d)
     * @return array ['logs' => [], 'total' => int, 'pages' => int]
     */
    public function getBlockLogs($limit = 100, $page = 1, $dateFrom = null, $dateTo = null) {
        $logFile = __DIR__ . '/src/ip_block_log.json';
        if (!file_exists($logFile)) return ['logs' => [], 'total' => 0, 'pages' => 0];
        
        $logs = json_decode(file_get_contents($logFile), true) ?: [];
        
        // 날짜 필터 적용
        if ($dateFrom || $dateTo) {
            $logs = array_filter($logs, function($log) use ($dateFrom, $dateTo) {
                $logDate = substr($log['time'] ?? '', 0, 10);
                if ($dateFrom && $logDate < $dateFrom) return false;
                if ($dateTo && $logDate > $dateTo) return false;
                return true;
            });
        }
        
        // 역순 정렬 (최신순)
        $logs = array_reverse($logs);
        $total = count($logs);
        $pages = ceil($total / $limit);
        $offset = ($page - 1) * $limit;
        
        return [
            'logs' => array_slice($logs, $offset, $limit),
            'total' => $total,
            'pages' => $pages
        ];
    }
    
    /**
     * 차단 로그 선택 삭제
     * @param array $indices 삭제할 로그 인덱스들
     */
    public function deleteBlockLogsByIndex($indices) {
        $logFile = __DIR__ . '/src/ip_block_log.json';
        if (!file_exists($logFile)) return false;
        
        $logs = json_decode(file_get_contents($logFile), true) ?: [];
        $logs = array_reverse($logs); // 최신순으로
        
        // 인덱스 기준 삭제
        foreach ($indices as $idx) {
            unset($logs[$idx]);
        }
        
        $logs = array_values(array_reverse($logs)); // 원래 순서로 복원
        @file_put_contents($logFile, json_encode($logs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
        return true;
    }
    
    /**
     * 차단 로그 날짜 범위 삭제
     */
    public function deleteBlockLogsByDateRange($dateFrom, $dateTo) {
        $logFile = __DIR__ . '/src/ip_block_log.json';
        if (!file_exists($logFile)) return false;
        
        $logs = json_decode(file_get_contents($logFile), true) ?: [];
        $logs = array_filter($logs, function($log) use ($dateFrom, $dateTo) {
            $logDate = substr($log['time'] ?? '', 0, 10);
            if ($dateFrom && $logDate >= $dateFrom && (!$dateTo || $logDate <= $dateTo)) return false;
            return true;
        });
        
        @file_put_contents($logFile, json_encode(array_values($logs), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
        return true;
    }
    
    /**
     * 차단 로그 초기화
     */
    public function clearBlockLogs() {
        $logFile = __DIR__ . '/src/ip_block_log.json';
        if (file_exists($logFile)) {
            @unlink($logFile);
        }
        return true;
    }
    
    /**
     * IP 캐시 초기화
     */
    public function clearIPCache() {
        if (is_dir($this->cacheDir)) {
            $files = glob($this->cacheDir . '/*.json');
            foreach ($files as $file) {
                @unlink($file);
            }
        }
        return true;
    }
    
    /**
     * 차단 페이지 표시
     */
    public function showBlockPage($result) {
        $message = $this->settings['block_message'] ?? '접근이 차단되었습니다.';
        
        http_response_code(403);
        header('Content-Type: text/html; charset=utf-8');
        
        echo '<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Access Denied</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; 
               display: flex; justify-content: center; align-items: center; 
               min-height: 100vh; margin: 0; background: #f5f5f5; }
        .container { text-align: center; padding: 40px; background: white; 
                     border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); max-width: 400px; }
        .icon { font-size: 64px; margin-bottom: 20px; }
        h1 { color: #e74c3c; margin: 0 0 15px 0; font-size: 24px; }
        p { color: #666; margin: 0; line-height: 1.6; }
        .info { margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 5px; font-size: 12px; color: #999; }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">🚫</div>
        <h1>Access Denied</h1>
        <p>' . htmlspecialchars($message) . '</p>
        <div class="info">
            Your IP: ' . htmlspecialchars($result['ip']) . '<br>
            ' . ($result['country'] ? 'Country: ' . htmlspecialchars($result['country']) : '') . '
        </div>
    </div>
</body>
</html>';
        exit;
    }
    
    /**
     * 브루트포스 차단 페이지 표시
     */
    public function showBruteForcePage($remaining) {
        $minutes = ceil($remaining / 60);
        
        http_response_code(429);
        header('Content-Type: text/html; charset=utf-8');
        header('Retry-After: ' . $remaining);
        
        echo '<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Too Many Attempts</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; 
               display: flex; justify-content: center; align-items: center; 
               min-height: 100vh; margin: 0; background: #f5f5f5; }
        .container { text-align: center; padding: 40px; background: white; 
                     border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); max-width: 400px; }
        .icon { font-size: 64px; margin-bottom: 20px; }
        h1 { color: #e67e22; margin: 0 0 15px 0; font-size: 24px; }
        p { color: #666; margin: 0; line-height: 1.6; }
        .timer { margin-top: 20px; padding: 15px; background: #fff3cd; border-radius: 5px; font-size: 18px; color: #856404; }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">⏳</div>
        <h1>Too Many Login Attempts</h1>
        <p>' . __h('ip_brute_force') . '</p>
        <div class="timer">
            ' . __('ip_try_again', $minutes) . '
        </div>
    </div>
</body>
</html>';
        exit;
    }
}

/**
 * 간편 함수: 접근 체크 및 차단
 */
function check_ip_block() {
    static $blocker = null;
    if ($blocker === null) {
        $blocker = new IPBlocker();
    }
    
    $result = $blocker->checkAccess();
    if ($result['blocked']) {
        $blocker->showBlockPage($result);
    }
    
    return $result;
}

/**
 * 간편 함수: 브루트포스 체크
 */
function check_bruteforce() {
    static $blocker = null;
    if ($blocker === null) {
        $blocker = new IPBlocker();
    }
    
    $result = $blocker->checkBruteforce();
    if ($result['blocked']) {
        $blocker->showBruteForcePage($result['remaining']);
    }
    
    return $result;
}