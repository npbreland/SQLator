# SQLator TODOs

## Definitely
- [x] Create tests for non-read queries
- [x] Make sure capitalization convention is consistent (SQLator vs Sqlator)
- [x] Create README that outlines basic usage, suggested use cases, pitfalls,
testing, etc.
- [ ] Publish composer package
- [ ] Test with a WordPress database
- [ ] To reduce number of tokens in the prompt, find a way to make the table schemas 
that are fed into the prompt shorter (what info can be removed or made shorter?) 
- [ ] Make compatible with other databases, not just MySQL. The major obstacle here
is that the command to show the table schema can differ among the other database
types. I could try to use the AI itself to give the correct syntax, or I could
try something like Doctrine.

## Maybe
- [ ] Allow other AIs besides OpenAI's
