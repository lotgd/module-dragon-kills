# Module: Dragon Kills
![Tests](https://github.com/lotgd/module-dragon-kills/workflows/Tests/badge.svg)

Adds the green dragon to the forest to challenge if the character reached level 15. Additionaly, it 
keeps track of the dragon kills in your realm and stores the kill count on the character model.

## API
### Events
- `e/lotgd/dragon-kills/kill` (`Module::DragonKilledEvent`)\
This module publishes this event if a dragon has been slain. It can be used to reset the character and
strip him from his achievements.

### Character Model Extension Methods
- `getDragonKillCount(): int`\
Returns the number of dragon a character has killed.

- `setDragonKillCount(int $killCount)`\
Sets the number of dragons a character has killed. Used internally and does not synchronize with the 
dragon kill log.

- `incrementDragonKillCountForCharacter()`\
Increments the number of dragons a character has killed by 1. Used internally and does not synchronize 
with the dragon kill log.

### Character Properties
- `int lotgd/module-dragon-kills/dk` (`Module::CharacterPropertyDragonKills`)
The number of dragons a character has killed (use `$c->getDragonKillCount()` to access it)

- `bool lotgd/module-dragon-kills/seenDragon` (`Module::CharacterPropertySeenDragon`)
True if the character has challenged the dragon already and lost. False if not.