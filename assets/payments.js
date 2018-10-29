function requestPayment(token) {
	if (window.ethereum){
        window.web3 = new Web3(ethereum);
        try {
            ethereum.enable();
        } catch (error) {
        	console.log(error)
        }

	}else if (window.web3){
		web3 = new Web3(web3.currentProvider);
	}
	else {
        alert("Please Install Metamask at First！")
        return ;
    }

	var xmlhttp;
	var formData = new FormData();
	if (window.XMLHttpRequest) {// code for IE7+, Firefox, Chrome, Opera, Safari
		xmlhttp = new XMLHttpRequest();
	}
	else {// code for IE6, IE5
		xmlhttp = new ActiveXObject("Microsoft.XMLHTTP");
	}
	xmlhttp.onreadystatechange = function () {
		if (xmlhttp.readyState == 4 && xmlhttp.status == 200) {
			location.reload();
		}
	}
	var erc_contract = web3.eth.contract(abiArray);
	var erc_contract_instance = erc_contract.at(contract_address);
	console.log('目标钱包:',target_address)
	console.log('合约地址',contract_address)
	erc_contract_instance.transfer(target_address, token * 10e17,function(error,result){
		if (error === null && result !== null) {
			console.log("交易完成",result);
			formData.append('orderid',order_id);
			formData.append('tx',result);
			xmlhttp.open("POST", "/hook/wc_erc20", true);
			xmlhttp.send(formData);
		}
	})
}