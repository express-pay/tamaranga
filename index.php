<?php

class Plugin_Expresspay_card_p1c4f8b extends Plugin
{
    public function init()
    {
        parent::init();

        $this->setSettings(array(
            'extension_id'   => 'p1c4f8b8dda857e7b7628d3bb6ab719be9e3159e',
            'plugin_title'   => 'Экспресс Платежи: Интернет-экваринг',
            'plugin_version' => '1.0.0',
        ));

        /**
         * Настройки заполняемые в админ. панели
         */
        $this->configSettings(array(
            'isTest' => array(
                'title' => 'Тестовый режим',
                'description' => 'Использовать тестовый сервер для выставления счетов',
                'input' => 'checkbox',
            ),
            'serviceId' => array(
                'title' => 'Номер услуги',
                'required' => true,
                'input' => 'text',
                'description' => 'Можно узнать в личном кабинете сервиса "Экспресс Платежи" в настройках услуги.',
            ),
            'token' => array(
                'title' => 'Токен',
                'required' => true,
                'input' => 'text',
                'description' => 'Можно узнать в личном кабинете сервиса "Экспресс Платежи" в настройках услуги.',
            ),
            'useSignature' => array(
                'title' => 'Использовать цифровую подпись для выставления счетов',
                'description' => 'Значение должно совпадать со значением, установленным в личном кабинете сервиса "Экспресс Платежи".',
                'input' => 'checkbox',
            ),
            'secretWord' => array(
                'title' => 'Секретное слово',
                'input' => 'text',
                'description' => 'Задается в личном кабинете, секретное слово должно совпадать с секретным словом, установленным в личном кабинете сервиса "Экспресс Платежи".',
            ),
            'notifUrl' => array(
                'title' => 'Адрес для получения уведомлений',
                'input' => 'text',
                'default' => Bills::url('process', array('ps'=>$this->key())),
            ),
            'useSignatureForNotif' => array(
                'title' => 'Использовать цифровую подпись для уведомлений',
                'description' => 'Значение должно совпадать со значением, установленным в личном кабинете сервиса "Экспресс Платежи".',
                'input' => 'checkbox',
            ),
            'secretWordForNotif' => array(
                'title' => 'Секретное слово для уведомлений',
                'input' => 'text',
                'description' => 'Задается в личном кабинете, секретное слово должно совпадать с секретным словом, установленным в личном кабинете сервиса "Экспресс Платежи".',
            ),
        ));
    }

    protected function start()
    {
        // Код плагина
         # Дополняем список доступных пользователю способов оплаты
         bff::hooks()->billsPaySystemsUser(array($this, 'user_list'));

         # Дополняем данными о системе оплаты
         bff::hooks()->billsPaySystemsData(array($this, 'system_list'));
 
         # Форма выставленного счета, отправляемая системе оплаты
         bff::hooks()->billsPayForm(array($this, 'form'));
 
         # Обработка запроса от системы оплаты
        bff::hooks()->billsPayProcess(array($this, 'process'));
    }

    protected function id()
    {
        return 202;
    }

    protected function key()
    {
        return 'expresspay_card';
    }

    /**
     * Дополняем список доступных пользователю способов оплаты
     * @param array $list список систем оплат
     * @param array $extra: 'logoUrl', 'balanceUse'
     * @return array
     */
    public function user_list($list, $extra)
    {
        $list['expresspay_erip_plugin'] = array(
            'id'           => $this->id(),
            'logo_desktop' => $this->url('/logo.png'),
            'logo_phone'   => $this->url('/logo.png'),
            'way'          => '',
            'title'        => 'Экспресс Платежи: Интернет-эквайринг', # Название способа оплаты
            'currency_id'  => 2, # Рубли (ID валюты в системе)
            'enabled'      => true, # Способ доступен пользователю
            'priority'     => 0, # Порядок: 0 - последняя, 1+
        );

        return $list;
    }

    /**
     * Дополняем данными о системе оплаты
     * @param array $list
     * @return array
     */
    public function system_list($list)
    {
        $list[$this->id()] = array(
            'id'    => $this->id(),
            'key'   => $this->key(),
            # Название системы для описания счета в админ. панели
            'title' => 'Экспресс Платежи: Интернет-эквайринг',
            'desc'  => '',
        );
        return $list;
    }

    /**
     * Форма выставленного счета, отправляемая системе оплаты
     * @param string $form HTML форма
     * @param integer $paySystem ID системы оплаты для которой необходимо сформировать форму
     * @param array $data дополнительные данные о выставляемом счете:
     *  amount - сумма для оплаты
     *  bill_id - ID счета
     *  bill_description - описание счета
     *  bill_data = [currency_id]
     * @return string HTML
     */
    public function form($form, $paySystem, $data)
    {
        if ($paySystem != $this->id()) {
            return $form;
        }
        $fields = array(
            'ServiceId'         => $this->config('serviceId'),
            'AccountNo'         => $data['bill_id'],
            'Amount'            => number_format(floatval($data['amount']), 2, ',', ''),
            'Currency'          => 933,
            'ReturnType'        => 'redirect',
            'ReturnUrl'         => Bills::url('success') ,
            'FailUrl'           => Bills::url('fail'),
            'Expiration'        => '',
            'Info'              => $data['bill_description'],
        );

        # Подписываем
        $fields['Signature'] = $this->compute_signature($fields, $this->config('token'), $this->config('secretWord'));


        $baseUrl = "https://api.express-pay.by/v1/";
		
		if($this->config('isTest'))
			$baseUrl = "https://sandbox-api.express-pay.by/v1/";
		
		$url = $baseUrl . "web_cardinvoices";

        $form = '<form id="expressPayForm" style="display:none;" method="POST" action="'.$url.'">';
        foreach ($fields as $key => $val) {
            if (is_array($val)) {
               foreach ($val as $value) {
                    $form .= '<input type="hidden" name="'.$key.'" value="'.htmlspecialchars($value).'" />';
               }
            } else {
               $form .= '<input type="hidden" name="'.$key.'" value="'.htmlspecialchars($val).'" />';
            }
        }
        $form .= '</form>';
        return $form;
    }

    /**
     * Обработка запроса от системы оплаты
     * Метод должен завершать поток путем вызова bff::shutdown();
     * @param string $system ключ обрабываемой системы оплаты
     */
    public function process($system)
    {
        if ($system != $this->key()) return;

        $json = $_POST['Data'];
        $data = json_decode($json);
        $signature = $_POST['Signature'];

        if ($this->config('useSignatureForNotif') && $signature == $this->computeSignature($json, $this->config('secretWordForNotif')))
        {
            if($data->CmdType == '3' && $data->Status == '3' || '6')
            {
                # Обрабатываем счет
                $this->bills()->processBill($data->AccountNo, $data->Amount, $this->id());
                $status = 'OK | payment received';
			    echo($status);
			    header("HTTP/1.0 200 OK");
                bff::shutdown();
            }
        }
        else if (!isset($signature))
        {
            if($data->CmdType == '3' && $data->Status == '3' || '6')
            {
                # Обрабатываем счет
                $this->bills()->processBill($data->AccountNo, $data->Amount, $this->id());
                $status = 'OK | payment received';
			    echo($status);
			    header("HTTP/1.0 200 OK");
                bff::shutdown();
            }

        }
        else
        {
            $status = 'FAILED | wrong notify signature'; 
            echo($status);
            header("HTTP/1.0 400 Bad Request");
            bff::shutdown();
        }
    }

    protected function computeSignature($json, $secretWord)
    {
    $hash = NULL;
    
	$secretWord = trim($secretWord);
	
    if (empty($secretWord))
		$hash = strtoupper(hash_hmac('sha1', $json, ""));
    else
        $hash = strtoupper(hash_hmac('sha1', $json, $secretWord));
    return $hash;
    }

    protected function compute_signature($request_params, $token, $secret_word, $method = 'add_invoice') {
        $secret_word = trim($secret_word);
        $normalized_params = array_change_key_case($request_params, CASE_LOWER);
        $api_method = array( 
            'add_invoice' => array(
                                "serviceid",
                                "accountno",
                                "expiration",
                                "amount",
                                "currency",
                                "info",
                                "returnurl",
                                "failurl",
                                "language",
                                "sessiontimeoutsecs",
                                "expirationdate",
                                "returntype"),
            'add_invoice_return' => array(
                                "accountno"
            )
        );
    
        $result = $token;
    
        foreach ($api_method[$method] as $item)
            $result .= ( isset($normalized_params[$item]) ) ? $normalized_params[$item] : '';
    
        $hash = strtoupper(hash_hmac('sha1', $result, $secret_word));
    
        return $hash;
    }

}