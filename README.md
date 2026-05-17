# About

I started this project to learn the steps and approaches of LLM integration, with the goal of building something similar in a real commercial environment.
The REST API enables generating valid, executable SQL queries from plain-text user prompts using an LLM.

# Laravel LLM Backend

For AI calls, I used the **Laravel AI SDK**, which provides a unified interface for managing multiple LLM models and allows flexible swapping of models depending on which one is better suited for a given task — in this case, SQL query generation.

For LLM calls, I implemented **Laravel Queues** to ensure that time-intensive API calls do not degrade the user experience. Currently, I use a **polling** approach to retrieve responses. At low load levels — and given that the workplace system is not used on mobile primarly — this is a simple and efficient solution. As traffic grows, polling may introduce unnecessary overhead, at which point switching to an alternative mechanism (e.g. WebSockets or Server-Sent Events) would be worth considering.

API endpoint security is handled with **JWT tokens**, designed with future traffic growth in mind. I chose JWT over session-based auth because it is more flexible and requires no database lookups per request. Additionally, I implemented **two layers of rate limiting** to protect the endpoints:
- **Standard rate limiting** — caps the maximum number of requests a client can make within a given time window.
- **LLM cost protection** — limits each client to a maximum of **50 LLM calls per day** to guard against runaway API costs.