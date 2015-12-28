#### Штучное отслеживание:
>$pochtaApi = new PochtaApi();  
>$trackData = $pochtaApi->getOperationHistory($track);

#### Пакетное отслеживание:
>$pochtaApi = new PochtaApi();  
>$ticket = $pochtaApi->getTicket($tracksArray);    
>$trackData = $pochtaApi->getResponseByTicket($ticket); // этот запрос не ранее, чем через 15 минут 