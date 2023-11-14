var amqp = require('amqplib/callback_api');
const axios = require('axios')
const env = require('./env');


amqp.connect(env.base_amqp_url, function(error0, connection) {
    if (error0) {
        throw error0;
    }
    connection.createChannel(function(error1, channel) {
        if (error1) {
            throw error1;
        }
        var exchange = 'retina.audit_auto_complete';

        channel.assertExchange(exchange, 'x-delayed-message', {
            durable: true,
            arguments: {
                'x-delayed-type': 'direct'
            }
        });

        channel.assertQueue('audit_auto_complete', {
            exclusive: false
        }, function(error2, q) {
            if (error2) {
                throw error2;
            }
            console.log(" [*] Waiting for messages in %s. To exit press CTRL+C", q.queue);
            channel.bindQueue(q.queue, exchange, '');

            channel.consume(q.queue, function(msg) {
                if (msg.content) {
                    console.log(" [x] %s", msg.content.toString());

                    const obj = JSON.parse(msg.content.toString());
                    var currentdate = new Date();
                    var now = currentdate.getFullYear() + "-" +
                        String(currentdate.getMonth() + 1).padStart(2, '0') + "-" +
                        String(currentdate.getDate()).padStart(2, '0') + " " +
                        currentdate.getHours() + ":" +
                        currentdate.getMinutes() + ":" +
                        currentdate.getSeconds();
                    const config = {
                        headers: {
                            tokenId: obj.token,
                            'Content-Type': 'application/x-www-form-urlencoded'
                        }
                    };
                    axios.get(env.base_url + '/backend/audits/check-is-reviewed/' + obj.id, config)
                        .then(function(response) {
                            // handle success
                            console.log(response.data.bool);
                            // console.log('http://localhost/mqaaonline/backend/audits/' + obj.id)
                            if (!response.data.bool) {
                                axios.post(env.base_url + '/backend/audits/' + obj.id, {
                                        completed_at: now,
                                        vss_pic: obj.vss_pic,
                                        // lastName: 'Flintstone'
                                    }, config)
                                    .then(function(response) {
                                        console.log(response.data);
                                    })
                                    .catch(function(error) {
                                        console.log(error);
                                    });
                            }

                        })
                        .catch(function(error) {
                            // handle error
                            console.log(error);
                        })

                }
            }, {
                noAck: true
            });
        });
    });
});