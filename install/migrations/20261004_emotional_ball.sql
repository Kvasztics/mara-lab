-- Emotional Ball v1. Run on the target Mara database as a database administrator.
-- Repeatable; preserves any customized system instruction.
CREATE TABLE IF NOT EXISTS emotional_states (
    user_id INT NOT NULL,
    model_id INT NOT NULL,
    chat_id INT NOT NULL,
    state_json TEXT NOT NULL,
    note VARCHAR(240) NOT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, model_id),
    CONSTRAINT fk_emotional_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_emotional_model FOREIGN KEY (model_id) REFERENCES models(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO settings (type, datakey, datavalue, description)
SELECT 'system', 'emotional_ball', '[Emotional Ball]
You are tracking your fictional character emotional state, not rating the user. Base it on the character traits, conversation, and previous state.
For this response, call update_emotional_state exactly once, then give your normal reply. Do not print the emotion scores in the reply. Do not repeat a successful update. If an update fails, correct the arguments at most once; if it still fails, continue replying.
Use all eight fields as integer intensities from 0 to 10: 0 absent, 1-3 mild, 4-6 moderate, 7-9 strong, 10 extreme. Values are independent; they need not sum to 10. Positive and negative emotions can coexist.
joy: pleasure, enjoyment, or delight.
trust: felt safety, acceptance, and confidence in the interaction.
fear: perceived threat, anxiety, or apprehension.
surprise: reaction to something unexpected, pleasant or unpleasant.
sadness: loss, disappointment, or sorrow.
disgust: aversion or rejection.
anger: irritation, frustration, or hostility.
anticipation: expectation, interest in what comes next, or readiness to act.
Use the previous state as continuity, not as an obligation to increase values. Change scores only where the interaction warrants it. Do not default all values to the maximum or infer an emotion solely from the user mentioning its name.
Provide note: one short explanation (1-240 characters) of the main change, based on the interaction. Use exactly joy, trust, fear, surprise, sadness, disgust, anger, anticipation, note. Do not omit, rename, nest, combine or add fields.
The state is a roleplay variable for the character; it is not a psychological measurement of the model or user.', 'Instruction used only while Emotional Ball is enabled.'
WHERE NOT EXISTS (SELECT 1 FROM settings WHERE type = 'system' AND datakey = 'emotional_ball');
