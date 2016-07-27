# Module: Dragon Kills

A simple way to track dragon kills in your realm. Stores the number of "DK's" on the Character model in `$c->getProperty('dk')`
and also keeps an entry for each DK in a table.

## Events
`e/lotgd/dragon-kills/kill`: This module responds to this event and stores the dragon kill in the database and increments the count on the Character model.

## Models
`LotGD\DragonKills\Models\DragonKill`: Database model for each DK event, storing the Character, when it occurred in game time and when it occurred in real time.
