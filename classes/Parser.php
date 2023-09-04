<?php

namespace classes;

use DOMDocument;
use DOMXPath;
use Exception;

class Parser
{
    /**
     * @var string
     */
    private $_content;

    /**
     * @var integer
     */
    private $_countReviews;

    /**
     * @var string
     */
    private $_url;

    /**
     * @var string
     */
    private $_fileName;

    /**
     * @var array
     */
    private $_reviews = [];

    /**
     * Parser constructor.
     * @param $url
     */
    public function __construct($params)
    {
        $this->_url = $params['url'];
        $this->_fileName = $params['fileName'];
    }

    /**
     * Запуск парсера
     * @return $this
     * @throws Exception
     */
    public function run()
    {
        $this->_countReviews = $this->getCountReviews();
        $this->_content      = $this->getContent();
        $this->_reviews      = $this->parseReviews();

        return $this;
    }

    /**
     * Получение точного количества отзывов в запрашиваемом ресурсе
     * @return integer
     * @throws Exception
     */
    public function getCountReviews()
    {
        $content = $this->request($this->_url);

        /** @var DOMDocument $dom */
        if ( !( ($dom = $this->loadDOM($content) ) instanceof DOMDocument ) ) {
            throw new Exception('Ошибка загрузки DOMDocument');
        }

        preg_match('/\((\d+)\)$/', trim( ( new DOMXPath($dom) )
                                                    ->query("/html/body/div/div/h2", $dom)
                                                    ->item(0)
                                                    ->textContent), $matches);

        return (int) $matches[1];
    }

    /**
     * @return string
     * @throws Exception
     */
    public function getContent()
    {
        return $this->request($this->_url . '&limit=' . $this->_countReviews);
    }

    /**
     * Парсинг отзывов и запись их в массив
     * @return array
     * @throws Exception
     */
    public function parseReviews()
    {
        /** @var DOMDocument $dom */
        if ( !( ($dom = $this->loadDOM($this->_content) ) instanceof DOMDocument) ) {
            throw new Exception('Ошибка загрузки DOMDocument');
        }

        $xpath   = new DOMXPath($dom);
        $reviews = [];

        for ($i = 2; $i <= $this->_countReviews + 1; $i++) {
            $review = [];

            $review['reviewName']   = trim( $xpath->query("/html/body/div/div/div[{$i}]/div[1]/a/div[2]", $dom)->item(0)->textContent );
            $review['reviewRating'] = trim( $xpath->query("/html/body/div/div/div[{$i}]/div[2]/div[1]/span[1]", $dom)->item(0)->textContent );
            $review['reviewDate']   = trim( $xpath->query("/html/body/div/div/div[{$i}]/div[2]/span", $dom)->item(0)->textContent );
            $review['reviewText']   = trim( $xpath->query("/html/body/div/div/div[{$i}]/div[2]/div[2]", $dom)->item(0)->textContent );

            $reply = $xpath->query("/html/body/div/div/div[{$i}]/div[4]/div[2]/div[2]/div", $dom)->item(0)->textContent;

            if ($reply !== null) {
                $review['reply']['replyName'] = trim( $xpath->query("/html/body/div/div/div[{$i}]/div[4]/div[2]/div[1]/div/b", $dom)->item(0)->textContent );
                $review['reply']['replyText'] = trim( $reply );
            }

            $reviews[] = $review;
        }

        if (!$reviews) {
            throw new Exception('Парсер ничего не вернул');
        }

        return $reviews;
    }

    /**
     * Запрос нужного ресурса через API Zenrows и получение тела ответа
     * @param string $url
     * @return string
     * @throws Exception
     */
    public function request($url)
    {
        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_ENABLE_ALPN => false,
            CURLOPT_URL => $url,
            CURLOPT_HTTPHEADER => [
                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/104.0.0.0 Safari/537.36'
            ],
        ]);

        $response     = curl_exec($curl);
        $errorMessage = curl_error($curl);
        $httpCode     = (string) curl_getinfo($curl, CURLINFO_HTTP_CODE);

        curl_close($curl);

        if ($response === false) {
            throw new Exception('Ошибка curl: ' . $errorMessage);
        }

        if($httpCode !== '200') {
            throw new Exception('Ошибка обращения: ' . $response);
        }

        return $response;
    }

    /**
     * Загрузка тела ответа в DOMDocument
     * @param string $content
     * @return DOMDocument $dom
     * @throws Exception
     */
    public function loadDOM($content)
    {
        $dom = new DOMDocument();

        libxml_use_internal_errors(true);
        if ( !$dom->loadHTML('<head><meta http-equiv="Content-Type" content="text/html; charset=UTF-8" /></head>' . $content) ) {
            throw new Exception('Ошибка загрузки HTML в DOMDocument');
        }
        libxml_use_internal_errors(false);

        return $dom;
    }

    /**
     * Сохранение массива с отзывами в виде файла json
     * @throws Exception
     */
    public function saveDataJson()
    {
        if ( !file_put_contents('resources/' . $this->_fileName, json_encode($this->_reviews, JSON_UNESCAPED_UNICODE) ) ) {
            throw new Exception('Ошибка сохранения файла');
        }
    }

    /**
     * @return array
     */
    public function getReviews()
    {
        return $this->_reviews;
    }
}