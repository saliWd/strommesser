

sql command on old db:
SELECT * FROM `verbrauch` WHERE `zeit` < "2025-12-31 23:59:59" AND `copied` = 0 ORDER BY `id` DESC; 

gets me ~516 entries (exact numer may vary, depends on thinning operations)

First one:
id      usr cons        consDiff    nt        ntDiff   ht        htDiff   gen        genDiff  zeit                 zeitDiff thin copied   
32756     1	1231.327 	0.704 	635.684 	0.726 	595.585 	0.000 	2746.682 	0.000 	2025-12-31 23:00:43 	3642 	1 	0

-> easy, only ntDiff, apply the ntRate (2025: HT:0.3318, NT:0.2718, GEN:0.1500)
`id` - (TODO: will be big but date is old)
`userid` userid
`con` cons
`conDiff` consDiff
`conRate` HT (0.3318)
`gen` gen
`genDiff` genDiff
`genRate` 0.1500,
`zeit` zeit
`zeitDiff` zeitDiff
`thin` thin

