DROP TABLE IF EXISTS quote;

CREATE TABLE quote (
	quoteId binary(16) NOT NULL,
	quote VARCHAR(256) NOT NULL,
	quoteAuthor VARCHAR(64) NOT NULL,
	quotePoster VARCHAR(64) NOT NULL,
	quoteRating INT NOT NULL,
	INDEX (quote),
	INDEX (quoteAuthor),
	PRIMARY KEY (quoteId)
);
