import BusinessLink from "../BusinessLink";

import "./AboutSection.scss";

const AboutSection = ({ name }) => {
  return (
    <section className="about-section">
      <div className="container about-section__container">
        <div className="about-section__content">
          <span className="section-label">About {name}</span>

          <h2 className="section-title">
            Good Hair.
            <br />
            Good Service.
          </h2>

          <p>
            {name} is a modern barbering business focused on quality cuts, clean fades and
            professional grooming.
          </p>

          <p>
            Our goal is simple: provide every customer with a comfortable experience and a haircut
            they feel confident wearing.
          </p>

          <div className="about-section__points">
            <div>
              <strong>Quality</strong>
              <span>Professional barbering</span>
            </div>

            <div>
              <strong>Comfort</strong>
              <span>Relaxed environment</span>
            </div>

            <div>
              <strong>Convenience</strong>
              <span>Easy online booking</span>
            </div>
          </div>

          <BusinessLink to="/about" className="btn btn-primary">
            Learn More
          </BusinessLink>
        </div>
      </div>
    </section>
  );
};

export default AboutSection;
