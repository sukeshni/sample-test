*Refactoring

If I am the manager of this project and if I could change the specification of this app, I would

1. Consider RESTful principles to handle CRUD actions :
*Change the following endpoint
- POST api/companies/events ---> GET api/companies/events

Because the endpoint performs the read/retrieve/search records from the database.

*Change the following endpoint
- POST api/users/reserve
	Reserve ---> PUT api/users/:event_id/reserve
	Unreserve ---> DELETE api/users/:event_id/reserve.
Because the endpoint performs the update/modify and delete/destroy operation on the database

2. Each request to API comes with "token" field. This token is to be used for identifying users. Generation of token should be such that, it helps identify user, also gives expiry information.

3. Pagination data in response can be included so that clients can handle the data well. Also a readymade link n the response to request next set of requests for the events list.

--------------------------------------------------------
Good parts of the API according to me :

1. The API depends on "token" that comes with each request to identify users, this makes the api purely stateless.

2. Error handling is done in the API very well. E.g use of code and message field in the JSON response.
